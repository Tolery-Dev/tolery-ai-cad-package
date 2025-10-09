<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
     */
    public function generateCadStream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|string',
            'is_edit_request' => 'nullable|boolean',
        ]);

        $message = $validated['message'];
        $sessionId = $validated['session_id'] ?? null;

        return new StreamedResponse(function () use ($message, $sessionId) {
            // Headers SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Nginx buffering disabled

            // Flush output buffers
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();

            try {
                // Génère le streaming via AICADClient
                foreach ($this->client->generateCadStream($message, $sessionId, 600) as $event) {
                    // Format SSE
                    $eventType = $event['type'] ?? 'unknown';

                    if ($eventType === 'final') {
                        // Événement final avec final_response
                        $data = json_encode([
                            'final_response' => $event['final_response'],
                        ]);
                        echo "data: {$data}\n\n";
                    } elseif ($eventType === 'progress') {
                        // Événement de progression
                        $data = json_encode([
                            'step' => $event['step'] ?? '',
                            'status' => $event['status'] ?? '',
                            'message' => $event['message'] ?? '',
                            'overall_percentage' => $event['overall_percentage'] ?? 0,
                        ]);
                        echo "data: {$data}\n\n";
                    }

                    flush();
                }

                // Événement de fin
                echo "data: [DONE]\n\n";
                flush();
            } catch (\Exception $e) {
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
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
