<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugApiStream extends Command
{
    public $signature = 'ai-cad:debug-stream {--message=Create a simple 100x100x5mm steel plate}';

    public $description = 'Debug the SSE stream from AI CAD API (raw output)';

    public function handle(): int
    {
        $this->info('🔍 Debug SSE Stream from AI CAD API');
        $this->newLine();

        $apiUrl = config('ai-cad.api.base_url');
        $apiKey = config('ai-cad.api.key');
        $message = $this->option('message');

        if (! $apiUrl || ! $apiKey) {
            $this->error('❌ API URL or API KEY not configured');

            return self::FAILURE;
        }

        $url = rtrim($apiUrl, '/').'/api/generate-cad-stream';

        $this->line("📡 URL: {$url}");
        $this->line("💬 Message: {$message}");
        $this->newLine();

        try {
            // Test 1: Simple GET without streaming
            $this->info('Test 1: Simple GET request (non-streaming)');
            $response = Http::timeout(10)
                ->withToken($apiKey)
                ->acceptJson()
                ->get($url, [
                    'message' => $message,
                    'project_id' => null,
                    'stream' => 'true',
                ]);

            $this->line('Status: '.$response->status());
            $this->line('Content-Type: '.$response->header('Content-Type'));
            $this->line('Body length: '.strlen($response->body()));
            $this->line('Body preview (first 500 chars):');
            $this->line(substr($response->body(), 0, 500));
            $this->newLine();

            // Test 2: Streaming request
            $this->info('Test 2: Streaming GET request');
            $streamResponse = Http::timeout(120)
                ->withToken($apiKey)
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->withOptions(['stream' => true])
                ->get($url, [
                    'message' => $message,
                    'project_id' => null,
                    'stream' => 'true',
                ]);

            $psr = $streamResponse->toPsrResponse();
            $body = $psr->getBody();

            $this->line('PSR Status: '.$psr->getStatusCode());
            $this->line('PSR Content-Type: '.($psr->getHeader('Content-Type')[0] ?? 'N/A'));
            $this->line('PSR Body is readable: '.($body->isReadable() ? 'Yes' : 'No'));
            $this->line('PSR Body is seekable: '.($body->isSeekable() ? 'Yes' : 'No'));
            $this->newLine();

            $this->info('Reading chunks (first 10):');
            $chunkCount = 0;
            $maxChunks = 10;

            while (! $body->eof() && $chunkCount < $maxChunks) {
                $chunk = $body->read(8192);
                if ($chunk === '') {
                    usleep(100_000); // 100ms
                    continue;
                }

                $chunkCount++;
                $this->line("Chunk #{$chunkCount} (length: ".strlen($chunk).')');
                $this->line('Content: '.substr($chunk, 0, 200));
                $this->newLine();
            }

            if ($chunkCount === 0) {
                $this->error('❌ No chunks received from stream!');

                return self::FAILURE;
            }

            $this->info("✅ Received {$chunkCount} chunks");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
