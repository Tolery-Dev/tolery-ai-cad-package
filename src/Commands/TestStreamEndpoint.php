<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestStreamEndpoint extends Command
{
    public $signature = 'ai-cad:test-endpoint {--message=Create a simple 100x100x5mm steel plate}';

    public $description = 'Test the Laravel streaming endpoint (simulates frontend)';

    public function handle(): int
    {
        $this->info('🧪 Testing Laravel SSE Endpoint');
        $this->newLine();

        $message = $this->option('message');
        $baseUrl = config('app.url', 'http://localhost');
        $url = rtrim($baseUrl, '/').'/ai-cad/stream/generate-cad';

        $this->line("📡 Endpoint: {$url}");
        $this->line("💬 Message: {$message}");
        $this->newLine();

        $this->info('⚠️  Note: This test requires authentication. It will fail if you don\'t have a valid session.');
        $this->newLine();

        try {
            $this->info('🚀 Starting SSE stream...');
            $response = Http::timeout(120)
                ->withHeaders([
                    'Accept' => 'text/event-stream',
                    'Content-Type' => 'application/json',
                ])
                ->withOptions(['stream' => true])
                ->post($url, [
                    'message' => $message,
                    'session_id' => null,
                ]);

            $psr = $response->toPsrResponse();
            $body = $psr->getBody();

            $this->line('Status: '.$psr->getStatusCode());
            $this->line('Content-Type: '.($psr->getHeader('Content-Type')[0] ?? 'N/A'));
            $this->newLine();

            if ($psr->getStatusCode() === 401) {
                $this->error('❌ Authentication required. This endpoint needs auth middleware.');

                return self::FAILURE;
            }

            if ($psr->getStatusCode() !== 200) {
                $this->error('❌ Unexpected status code: '.$psr->getStatusCode());
                $this->line('Body: '.$body->getContents());

                return self::FAILURE;
            }

            $this->info('📥 Reading SSE events:');
            $this->newLine();

            $buffer = '';
            $eventCount = 0;
            $maxEvents = 50;

            while (! $body->eof() && $eventCount < $maxEvents) {
                $chunk = $body->read(8192);
                if ($chunk === '') {
                    usleep(100_000); // 100ms
                    continue;
                }

                $buffer .= $chunk;

                // Process SSE packets
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $packet = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $packet) as $line) {
                        $line = trim($line);

                        // Skip empty lines and comments
                        if ($line === '' || str_starts_with($line, ':')) {
                            if (str_starts_with($line, ':')) {
                                $this->line("💬 Comment: {$line}");
                            }
                            continue;
                        }

                        // Parse data lines
                        if (str_starts_with($line, 'data:')) {
                            $eventCount++;
                            $json = trim(substr($line, 5));

                            if ($json === '[DONE]') {
                                $this->info('✅ Stream completed with [DONE] marker');

                                return self::SUCCESS;
                            }

                            try {
                                $payload = json_decode($json, true);
                                $this->line("Event #{$eventCount}: ".json_encode($payload, JSON_PRETTY_PRINT));
                            } catch (\Exception $e) {
                                $this->warn("⚠️  Failed to parse JSON: {$json}");
                            }
                        }
                    }
                }
            }

            if ($eventCount === 0) {
                $this->error('❌ No events received from stream!');

                return self::FAILURE;
            }

            $this->newLine();
            $this->info("✅ Received {$eventCount} events");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
