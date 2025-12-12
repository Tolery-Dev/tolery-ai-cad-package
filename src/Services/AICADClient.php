<?php

namespace Tolery\AiCad\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

readonly class AICADClient
{
    /**
     * GET /api/generate-cad-stream — SSE progress + final payload (Direct Output)
     *
     * This method streams SSE events directly to the output buffer (echo).
     * Uses native curl for reliable SSE streaming (Guzzle has issues with long streams).
     *
     * @throws \RuntimeException
     */
    public function streamDirectlyToOutput(string $message, ?string $projectId = null, bool $isEditRequest = false, int $timeoutSec = 600): void
    {
        $url = $this->endpoint('/api/generate-cad-stream');

        // Build query parameters for GET request
        $queryParams = [
            'message' => $message,
            'stream' => 'true',
        ];

        if ($projectId !== null) {
            $queryParams['session_id'] = $projectId;
        }

        if ($isEditRequest) {
            $queryParams['is_edit_request'] = 'true';
        }

        $fullUrl = $url.'?'.http_build_query($queryParams);
        $bearerToken = config('ai-cad.api.key', '');

        Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        Log::info('[AICAD] 🚀 NEW CAD GENERATION REQUEST');
        Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        Log::info('[AICAD] 📍 API Endpoint: '.$fullUrl);
        Log::info('[AICAD] 🔑 Session ID: '.($projectId ?? 'NEW SESSION (no ID provided)'));
        Log::info('[AICAD] 📝 Message: '.substr($message, 0, 150).(strlen($message) > 150 ? '...' : ''));
        Log::info('[AICAD] ✏️  Is Edit Request: '.($isEditRequest ? 'YES' : 'NO'));
        Log::info('[AICAD] ⏱️  Timeout: '.$timeoutSec.'s');
        Log::info('[AICAD] 🔐 Auth Token: '.(! empty($bearerToken) ? 'Present ('.strlen($bearerToken).' chars)' : 'MISSING'));
        Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $eventCount = 0;
        $bytesReceived = 0;
        $buffer = ''; // Buffer to parse SSE events for debugging

        // Initialize curl
        $ch = curl_init($fullUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$eventCount, &$bytesReceived, &$buffer, $projectId) {
                $length = strlen($data);
                $bytesReceived += $length;

                // Echo data directly to output (transparent proxy)
                echo $data;
                flush();

                // Count events for logging (data: lines)
                if (str_contains($data, 'data:')) {
                    $eventCount += substr_count($data, 'data:');

                    // Log every 10th event
                    if ($eventCount % 10 === 0) {
                        Log::debug("[AICAD] 📊 Progress: {$eventCount} events streamed ({$bytesReceived} bytes)");
                    }
                }

                // Parse SSE events to extract final_response URLs
                $buffer .= $data;
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $packet = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    // Look for data: lines
                    foreach (explode("\n", $packet) as $line) {
                        $line = ltrim($line);
                        if (str_starts_with($line, 'data:')) {
                            $json = trim(substr($line, 5));
                            if ($json !== '' && $json !== '[DONE]') {
                                $payload = json_decode($json, true);

                                // Log final_response with file URLs
                                if (is_array($payload) && isset($payload['final_response'])) {
                                    $final = $payload['final_response'];
                                    Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                    Log::info('[AICAD] ✅ GENERATION COMPLETED - Final Response Received');
                                    Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                    Log::info('[AICAD] 🔑 Session ID: '.($projectId ?? 'N/A'));

                                    if (isset($final['obj_export'])) {
                                        Log::info('[AICAD] 📦 OBJ File: '.$final['obj_export']);
                                    }
                                    if (isset($final['step_export'])) {
                                        Log::info('[AICAD] 📐 STEP File: '.$final['step_export']);
                                    }
                                    if (isset($final['tessellated_export']) && $final['tessellated_export']) {
                                        Log::info('[AICAD] 🔺 Tessellated File: '.$final['tessellated_export']);
                                    }
                                    if (isset($final['attribute_and_transientid_map']) && $final['attribute_and_transientid_map']) {
                                        Log::info('[AICAD] 🗺️  Attribute Map: '.$final['attribute_and_transientid_map']);
                                    }
                                    if (isset($final['technical_drawing']) && $final['technical_drawing']) {
                                        Log::info('[AICAD] 📄 Technical Drawing: '.$final['technical_drawing']);
                                    }
                                    if (isset($final['screenshot']) && $final['screenshot']) {
                                        Log::info('[AICAD] 📸 Screenshot: '.$final['screenshot']);
                                    }
                                    if (isset($final['manufacturing_errors']) && ! empty($final['manufacturing_errors'])) {
                                        Log::warning('[AICAD] ⚠️  Manufacturing Errors: '.json_encode($final['manufacturing_errors']));
                                    }
                                    if (isset($final['chat_response'])) {
                                        Log::info('[AICAD] 💬 Chat Response: '.substr($final['chat_response'], 0, 200).(strlen($final['chat_response']) > 200 ? '...' : ''));
                                    }
                                    Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                }
                            }
                        }
                    }
                }

                return $length;
            },
            CURLOPT_HTTPHEADER => [
                'Accept: text/event-stream',
                'Authorization: Bearer '.$bearerToken,
            ],
        ]);

        // Execute curl request
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Check for errors
        if (! $success || $curlErrno !== 0) {
            Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            Log::error('[AICAD] ❌ CURL STREAM FAILED');
            Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            Log::error('[AICAD] 🔑 Session ID: '.($projectId ?? 'N/A'));
            Log::error('[AICAD] 📍 API Endpoint: '.$fullUrl);
            Log::error('[AICAD] ⚠️  Error: '.$curlError);
            Log::error('[AICAD] 🔢 Error Code: '.$curlErrno);
            Log::error('[AICAD] 📊 HTTP Code: '.$httpCode);
            Log::error('[AICAD] 📦 Bytes Received: '.$bytesReceived);
            Log::error('[AICAD] 📨 Events Received: '.$eventCount);
            Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            echo 'data: '.json_encode([
                'error' => true,
                'message' => "Stream failed: {$curlError}",
            ])."\n\n";
            flush();

            throw new \RuntimeException("SSE stream failed: {$curlError}", $curlErrno);
        }

        if ($httpCode !== 200) {
            Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            Log::error('[AICAD] ❌ NON-200 HTTP RESPONSE');
            Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            Log::error('[AICAD] 🔑 Session ID: '.($projectId ?? 'N/A'));
            Log::error('[AICAD] 📍 API Endpoint: '.$fullUrl);
            Log::error('[AICAD] 📊 HTTP Code: '.$httpCode);
            Log::error('[AICAD] 📦 Bytes Received: '.$bytesReceived);
            Log::error('[AICAD] 📨 Events Received: '.$eventCount);
            Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            echo 'data: '.json_encode([
                'error' => true,
                'message' => "API returned status {$httpCode}",
            ])."\n\n";
            flush();

            throw new \RuntimeException("API returned status {$httpCode}");
        }

        Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        Log::info('[AICAD] ✅ STREAM COMPLETED SUCCESSFULLY');
        Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        Log::info('[AICAD] 🔑 Session ID: '.($projectId ?? 'N/A'));
        Log::info('[AICAD] 📨 Total Events: '.$eventCount);
        Log::info('[AICAD] 📦 Total Bytes: '.number_format($bytesReceived).' bytes ('.round($bytesReceived / 1024, 2).' KB)');
        Log::info('[AICAD] 📊 HTTP Code: '.$httpCode);
        Log::info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * GET /api/generate-cad-stream — SSE progress + final payload (Generator)
     *
     * This method yields parsed SSE events as arrays (for CLI commands).
     * For web requests, use streamDirectlyToOutput() instead.
     *
     * Query parameters: message, session_id (optional), stream=true
     *
     * Events (exemples reçus):
     *  - {"step":"analysis","status":"Analyzing user requirements...","overall_percentage":10,...}
     *  - ...
     *  - {"final_response":{"chat_response":"...","obj_export":"...","step_export":"...","tessellated_export":null,"attribute_and_transientid_map":null,"manufacturing_errors":[]}}
     *
     * Yields:
     *  - ['type'=>'progress', 'step'=>string, 'status'=>string, 'message'=>string, 'overall_percentage'=>int]
     *  - ['type'=>'final', 'final_response'=>array{chat_response?:string,obj_export?:string,step_export?:string,tessellated_export?:?string,attribute_and_transientid_map?:?string,manufacturing_errors?:array}]
     *
     * @throws ConnectionException
     */
    public function generateCadStream(string $message, ?string $projectId = null, int $timeoutSec = 180): Generator
    {
        $url = $this->endpoint('/api/generate-cad-stream');

        // Build query parameters for GET request
        $queryParams = [
            'message' => $message,
            'stream' => 'true',
        ];

        if ($projectId !== null) {
            $queryParams['session_id'] = $projectId;
        }

        $resp = $this->http($timeoutSec)
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->withOptions(['stream' => true])
            ->get($url, $queryParams);

        $psr = $resp->toPsrResponse();
        $body = $psr->getBody();
        $buffer = '';
        $chunkCount = 0;
        $totalBytes = 0;

        while (! $body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                usleep(10_000);

                continue;
            }
            $chunkCount++;
            $totalBytes += strlen($chunk);

            // Debug: log first few chunks to see what we're receiving
            if ($chunkCount <= 3) {
                Log::info("AICADClient: Chunk #{$chunkCount}", [
                    'length' => strlen($chunk),
                    'preview' => substr($chunk, 0, 300),
                    'url' => $url,
                ]);
            }

            $buffer .= $chunk;

            // SSE: paquets séparés par \n\n
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $packet = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                Log::debug('AICADClient: SSE packet received', ['packet' => substr($packet, 0, 200)]);

                foreach (explode("\n", $packet) as $line) {
                    $line = ltrim($line);
                    if ($line === '' || Str::startsWith($line, ':')) {
                        Log::debug('AICADClient: Skipping line (empty or comment)', ['line' => substr($line, 0, 100)]);

                        continue;
                    }
                    if (! Str::startsWith($line, 'data:')) {
                        Log::warning('AICADClient: Non-data line encountered', ['line' => substr($line, 0, 100)]);

                        continue;
                    }

                    $json = trim(substr($line, 5));
                    if ($json === '' || $json === '[DONE]') {
                        Log::debug('AICADClient: Empty or DONE marker', ['json' => $json]);

                        continue;
                    }

                    $payload = json_decode($json, true);
                    if (! is_array($payload)) {
                        Log::warning('AICADClient: Failed to parse JSON', ['json' => substr($json, 0, 200)]);

                        continue;
                    }

                    Log::info('AICADClient: Valid payload parsed', ['payload_keys' => array_keys($payload)]);

                    if (isset($payload['final_response'])) {
                        yield [
                            'type' => 'final',
                            'final_response' => (array) $payload['final_response'],
                        ];

                        continue;
                    }

                    // Progress event
                    $step = (string) ($payload['step'] ?? '');
                    $status = (string) ($payload['status'] ?? '');
                    $msg = (string) ($payload['message'] ?? '');
                    $pct = (int) ($payload['overall_percentage'] ?? 0);

                    if ($step !== '' || $status !== '' || $msg !== '') {
                        yield [
                            'type' => 'progress',
                            'step' => $step,
                            'status' => $status,
                            'message' => $msg,
                            'overall_percentage' => $pct,
                        ];
                    }
                }
            }
        }

        // Debug: log if we received no events
        if ($chunkCount === 0 && config('app.debug')) {
            Log::warning('SSE stream ended with no chunks received', [
                'url' => $url,
                'status' => $psr->getStatusCode(),
                'headers' => $psr->getHeaders(),
            ]);
        } elseif (config('app.debug')) {
            Log::debug('SSE stream completed', [
                'chunks' => $chunkCount,
                'total_bytes' => $totalBytes,
            ]);
        }
    }

    /* ---------- HTTP helpers ---------- */
    protected function http(int $timeoutSec): PendingRequest
    {
        $req = Http::timeout($timeoutSec)->acceptJson();
        $key = config('ai-cad.api.key', '');
        if ($key) {
            $req = $req->withToken($key);
        }

        return $req;
    }

    protected function endpoint(string $path): string
    {
        return rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
    }

    protected function baseUrl(): string
    {
        return rtrim(config('ai-cad.api.base_url', ''), '/');
    }
}
