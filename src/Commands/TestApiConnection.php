<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Tolery\AiCad\Services\AICADClient;

class TestApiConnection extends Command
{
    public $signature = 'ai-cad:test-api {--message=Create a simple 100x100x5mm steel plate}';

    public $description = 'Test the connection to the AI CAD API';

    public function handle(): int
    {
        $this->info('🔧 Testing AI CAD API Connection...');
        $this->newLine();

        // 1. Check configuration
        $this->info('📋 Configuration:');
        $apiUrl = config('ai-cad.api.base_url');
        $apiKey = config('ai-cad.api.key');

        if (! $apiUrl) {
            $this->error('❌ AI_CAD_API_URL is not configured in .env');

            return self::FAILURE;
        }
        $this->line("   API URL: {$apiUrl}");

        if (! $apiKey) {
            $this->error('❌ AICAD_API_KEY is not configured in .env');

            return self::FAILURE;
        }
        $this->line('   API Key: '.str_repeat('*', strlen($apiKey) - 4).substr($apiKey, -4));
        $this->newLine();

        // 2. Test API streaming
        $this->info('🚀 Starting SSE stream test...');
        $message = $this->option('message');
        $this->line("   Message: {$message}");
        $this->newLine();

        try {
            $client = app(AICADClient::class);
            $progressBar = $this->output->createProgressBar(100);
            $progressBar->start();

            $lastPercentage = 0;
            $eventsReceived = 0;
            $finalResponse = null;
            $debug = $this->option('verbose');

            foreach ($client->generateCadStream($message, null, 120) as $event) {
                $eventsReceived++;
                $type = $event['type'] ?? 'unknown';

                if ($debug) {
                    $progressBar->clear();
                    $this->line('🔍 Event received: '.json_encode($event));
                    $progressBar->display();
                }

                if ($type === 'progress') {
                    $pct = $event['overall_percentage'] ?? 0;
                    if ($pct > $lastPercentage) {
                        $progressBar->setProgress($pct);
                        $lastPercentage = $pct;
                    }

                    $step = $event['step'] ?? '';
                    $status = $event['status'] ?? '';
                    if ($step) {
                        $progressBar->setMessage(" [{$step}] {$status}");
                    }
                } elseif ($type === 'final') {
                    $finalResponse = $event['final_response'];
                    $progressBar->finish();
                    break;
                }
            }

            $this->newLine(2);
            $this->info("✅ Stream completed! Received {$eventsReceived} events");
            $this->newLine();

            if ($finalResponse) {
                $this->info('📦 Final Response:');
                $this->line('   Chat Response: '.(isset($finalResponse['chat_response']) ? '✓' : '✗'));
                $this->line('   OBJ Export: '.(isset($finalResponse['obj_export']) && $finalResponse['obj_export'] ? '✓' : '✗'));
                $this->line('   STEP Export: '.(isset($finalResponse['step_export']) && $finalResponse['step_export'] ? '✓' : '✗'));
                $this->line('   JSON Export: '.(isset($finalResponse['json_export']) && $finalResponse['json_export'] ? '✓' : '✗'));
                $this->line('   Technical Drawing: '.(isset($finalResponse['technical_drawing_export']) && $finalResponse['technical_drawing_export'] ? '✓' : '✗'));
                $this->line('   Session ID: '.($finalResponse['session_id'] ?? 'N/A'));

                if (! empty($finalResponse['manufacturing_errors'])) {
                    $this->warn('⚠️  Manufacturing errors detected:');
                    foreach ($finalResponse['manufacturing_errors'] as $error) {
                        $this->line('   - '.json_encode($error));
                    }
                }

                $this->newLine();
                $this->info('🎉 API Test PASSED!');

                return self::SUCCESS;
            } else {
                $this->error('❌ No final response received from API');

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('❌ API Test FAILED!');
            $this->error('Error: '.$e->getMessage());
            $this->line('Trace: '.$e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
