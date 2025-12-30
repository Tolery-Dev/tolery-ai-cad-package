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
        $isEditRequest = $validated['is_edit_request'] ?? false;

        return new StreamedResponse(function () use ($message, $sessionId, $isEditRequest) {
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

            Log::info('[AICAD] StreamController: Received request from frontend', [
                'message' => substr($message, 0, 100),
                'session_id' => $sessionId,
                'session_id_empty' => empty($sessionId),
                'is_edit_request' => $isEditRequest,
                'php_version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
            ]);

            try {
                // Direct streaming: AICADClient will echo SSE events directly to output
                // No Generator, no foreach loop, no nested streaming
                $this->client->streamDirectlyToOutput($message, $sessionId, $isEditRequest, 600);

                Log::info('[AICAD] StreamController: Stream completed successfully', [
                    'session_id' => $sessionId,
                    'message_length' => strlen($message),
                ]);

                // Événement de fin SSE
                echo "data: [DONE]\n\n";
                flush();

            } catch (\Exception $e) {
                Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                Log::error('[AICAD] ❌ StreamController EXCEPTION');
                Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                Log::error('[AICAD] 🔑 Session ID: '.($sessionId ?: 'N/A'));
                Log::error('[AICAD] 📝 Message: '.substr($message, 0, 150));
                Log::error('[AICAD] ⚠️  Exception Type: '.get_class($e));
                Log::error('[AICAD] ⚠️  Exception Message: '.$e->getMessage());
                Log::error('[AICAD] 📍 Exception File: '.$e->getFile().':'.$e->getLine());
                Log::error('[AICAD] 🔍 Exception Code: '.$e->getCode());
                if ($e->getPrevious()) {
                    Log::error('[AICAD] 🔗 Previous Exception: '.get_class($e->getPrevious()).' - '.$e->getPrevious()->getMessage());
                }
                Log::error('[AICAD] 📚 Stack Trace:', [
                    'trace' => $e->getTraceAsString(),
                ]);
                Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                // Envoie une erreur au format SSE
                $error = json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'type' => get_class($e),
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
