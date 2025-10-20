<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tolery\AiCad\Services\AICADClient;

class StreamController extends Controller
{
    public function __construct(
        protected AICADClient $client
    ) {}

    /**
     * Proxifie le streaming SSE de l'API externe vers le frontend
     * Cette approche évite les problèmes CORS et sécurise le token API
     *
     * Architecture: StreamController → AICADClient::streamDirectlyToOutput() → API DFM
     * Pas de Generator, echo direct pour éviter le nested streaming
     */
    public function generateCadStream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1',
            'session_id' => 'nullable|string',
            'is_edit_request' => 'nullable|boolean',
        ]);

        $message = $validated['message'];
        $sessionId = $validated['session_id'] ?? null;

        return new StreamedResponse(function () use ($message, $sessionId) {
            // PHP Configuration: Disable timeouts and enable continuous execution
            set_time_limit(0);              // No PHP timeout
            ignore_user_abort(true);        // Keep running if client disconnects
            ini_set('output_buffering', 'off');  // Disable PHP output buffering
            ini_set('implicit_flush', '1'); // Auto-flush after every output

            // Clear ALL nested output buffers
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            // Headers SSE (also set in Response object below, but set here for immediate effect)
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Nginx: disable buffering for this request

            // Send initial SSE comment to establish connection
            // This ensures the client receives something immediately
            echo ": connected\n\n";
            flush();

            Log::info('StreamController: Starting SSE stream (direct mode)', [
                'message' => substr($message, 0, 100),
                'session_id' => $sessionId,
                'php_version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
            ]);

            try {
                // Direct streaming: AICADClient will echo SSE events directly to output
                // No Generator, no foreach loop, no nested streaming
                $this->client->streamDirectlyToOutput($message, $sessionId, 600);

                Log::info('StreamController: Stream completed successfully');

                // Événement de fin SSE
                echo "data: [DONE]\n\n";
                flush();

            } catch (\Exception $e) {
                Log::error('StreamController: Stream failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Envoie une erreur au format SSE
                $error = json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                ]);
                echo "data: {$error}\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Critical for Nginx/Cloudflare
        ]);
    }
}
