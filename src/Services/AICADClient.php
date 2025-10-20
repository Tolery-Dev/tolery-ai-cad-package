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
     * Use this for web requests where you need to proxy the SSE stream without Generator overhead.
     *
     * @throws ConnectionException
     */
    public function streamDirectlyToOutput(string $message, ?string $projectId = null, int $timeoutSec = 600): void
    {
        $url = $this->endpoint('/api/generate-cad-stream');

        // Build query parameters for GET request
        $queryParams = [
            'message' => $message,
            'stream' => 'true',
        ];

        if ($projectId !== null) {
            $queryParams['project_id'] = $projectId;
        }

        Log::info('AICADClient: Starting direct stream', ['url' => $url, 'timeout' => $timeoutSec]);

        $resp = $this->http($timeoutSec)
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->withOptions(['stream' => true])
            ->get($url, $queryParams);

        $psr = $resp->toPsrResponse();
        $statusCode = $psr->getStatusCode();

        if ($statusCode !== 200) {
            Log::error('AICADClient: Non-200 response', [
                'status' => $statusCode,
                'headers' => $psr->getHeaders(),
            ]);
            echo "data: " . json_encode(['error' => true, 'message' => "API returned status {$statusCode}"]) . "\n\n";
            flush();
            return;
        }

        $body = $psr->getBody();

        try {
            $buffer = '';
            $chunkCount = 0;
            $eventCount = 0;
            $maxBufferSize = 1024 * 1024; // 1MB safety limit

            while (! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '') {
                usleep(10_000); // 10ms
                continue;
            }

            $chunkCount++;
            $buffer .= $chunk;

            // Safety check: prevent buffer overflow
            if (strlen($buffer) > $maxBufferSize) {
                Log::error('AICADClient: Buffer overflow detected', [
                    'buffer_size' => strlen($buffer),
                    'chunk_count' => $chunkCount,
                ]);
                echo "data: " . json_encode(['error' => true, 'message' => 'Stream buffer overflow']) . "\n\n";
                flush();
                return;
            }

            // Log first few chunks for debugging
            if ($chunkCount <= 3) {
                Log::info("AICADClient: Chunk #{$chunkCount} received", [
                    'length' => strlen($chunk),
                    'preview' => substr($chunk, 0, 200),
                ]);
            }

            // Process complete SSE packets (separated by \n\n)
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $packet = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                // Echo the packet directly to output
                foreach (explode("\n", $packet) as $line) {
                    $line = trim($line);

                    // Skip empty lines
                    if ($line === '') {
                        continue;
                    }

                    // Echo SSE lines directly (data:, event:, id:, or comments)
                    if (Str::startsWith($line, 'data:') ||
                        Str::startsWith($line, 'event:') ||
                        Str::startsWith($line, 'id:') ||
                        Str::startsWith($line, ':')) {

                        echo $line . "\n";

                        // Count actual data events
                        if (Str::startsWith($line, 'data:')) {
                            $eventCount++;

                            // Log every 10th event
                            if ($eventCount % 10 === 0) {
                                Log::debug("AICADClient: Streamed {$eventCount} events");
                            }
                        }
                    }
                }

                // Complete the SSE packet with double newline
                echo "\n";
                flush();
            }
        }

            Log::info('AICADClient: Direct stream completed', [
                'chunks' => $chunkCount,
                'events' => $eventCount,
            ]);
        } finally {
            // Always close the stream to free resources
            if ($body && method_exists($body, 'close')) {
                $body->close();
            }
        }
    }

    /**
     * GET /api/generate-cad-stream — SSE progress + final payload (Generator)
     *
     * This method yields parsed SSE events as arrays (for CLI commands).
     * For web requests, use streamDirectlyToOutput() instead.
     *
     * Query parameters: message, project_id (optional), stream=true
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
            $queryParams['project_id'] = $projectId;
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
