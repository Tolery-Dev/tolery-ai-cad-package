<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Services\PredefinedPromptCacheService;

class CachePredefinedPromptsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai-cad:cache-prompts
                            {--force : Force regeneration of existing caches}
                            {--prompt= : Cache only a specific prompt number (1-4)}';

    /**
     * The console command description.
     */
    protected $description = 'Pre-generate and cache the 4 predefined prompts for ultra-fast responses';

    protected PredefinedPromptCacheService $cacheService;

    /**
     * Execute the console command.
     */
    public function handle(PredefinedPromptCacheService $cacheService): int
    {
        $this->cacheService = $cacheService;

        $this->info('🚀 Starting predefined prompts cache generation...');
        $this->newLine();

        $predefinedPrompts = config('ai-cad.cache.predefined_prompts', []);

        if (empty($predefinedPrompts)) {
            $this->error('❌ No predefined prompts found in config/ai-cad.php');

            return self::FAILURE;
        }

        $promptNumber = $this->option('prompt');
        $force = $this->option('force');

        // Extract prompts (values only, keys are labels for UI)
        $prompts = array_values($predefinedPrompts);

        // Filter to specific prompt if requested
        if ($promptNumber !== null) {
            $index = (int) $promptNumber - 1;
            if (! isset($prompts[$index])) {
                $this->error('❌ Invalid prompt number. Must be 1-'.count($prompts));

                return self::FAILURE;
            }
            $prompts = [$prompts[$index]];
            $this->info("📌 Caching only prompt #{$promptNumber}");
            $this->newLine();
        }

        $success = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($prompts as $index => $prompt) {
            $promptNum = $promptNumber ?? $index + 1;

            // Check if already cached
            if (! $force && $this->cacheService->isCached($prompt)) {
                $this->warn("⏭️  Prompt #{$promptNum}: Already cached (use --force to regenerate)");
                $skipped++;

                continue;
            }

            $this->info("⚙️  Prompt #{$promptNum}: Generating...");
            $this->line('   Message: '.substr($prompt, 0, 80).'...');

            try {
                $result = $this->generateAndCache($prompt);

                if ($result) {
                    $this->info("✅ Prompt #{$promptNum}: Successfully cached!");
                    $success++;
                } else {
                    $this->error("❌ Prompt #{$promptNum}: Failed to cache");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Prompt #{$promptNum}: Error - {$e->getMessage()}");
                Log::error('[CACHE CMD] Failed to cache prompt', [
                    'prompt' => substr($prompt, 0, 100),
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            $this->newLine();
        }

        // Summary
        $this->info('📊 Cache Generation Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Success', $success],
                ['⏭️  Skipped', $skipped],
                ['❌ Failed', $failed],
            ]
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Generate CAD for prompt and cache the result
     */
    protected function generateAndCache(string $prompt): bool
    {
        $apiUrl = config('ai-cad.api.url');
        $apiKey = config('ai-cad.api.key');

        if (empty($apiUrl) || empty($apiKey)) {
            $this->error('❌ AI CAD API URL or KEY not configured');

            return false;
        }

        // Call API with streaming to get final response
        $endpoint = rtrim($apiUrl, '/').'/api/generate-cad-stream';

        $this->line('   📡 Calling AI CAD API (this may take 1-5 minutes)...');

        try {
            // Use HTTP client with longer timeout for SSE stream
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'text/event-stream',
            ])
                ->timeout(600) // 10 minutes
                ->get($endpoint, [
                    'message' => $prompt,
                ]);

            if (! $response->successful()) {
                $this->error("   ❌ API Error: {$response->status()}");
                Log::error('[CACHE CMD] API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            // Parse SSE response
            $data = $this->parseSSEResponse($response->body());

            if ($data === null) {
                $this->error('   ❌ Failed to parse SSE response');
                Log::error('[CACHE CMD] SSE parsing failed', [
                    'body_preview' => substr($response->body(), 0, 1000),
                ]);

                return false;
            }

            // Extract file URLs from response
            $files = $this->downloadAndStoreFiles($data, $prompt);

            if (empty($files)) {
                $this->error('   ❌ No files generated');

                return false;
            }

            // Store in cache
            $this->cacheService->store(
                $prompt,
                $files,
                $data['chat_response'] ?? 'Pièce générée avec succès.',
                null // Will use default simulated steps
            );

            $this->line('   💾 Files cached: '.implode(', ', array_keys(array_filter($files))));

            return true;
        } catch (\Exception $e) {
            Log::error('[CACHE CMD] Exception during API call', [
                'error' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 100),
            ]);
            throw $e;
        }
    }

    /**
     * Download files from API response and store in cache directory
     */
    protected function downloadAndStoreFiles(array $data, string $prompt): array
    {
        $hash = $this->cacheService->generateHash($prompt);
        $storagePath = $this->cacheService->getStoragePath($hash);

        $files = [
            'obj' => null,
            'step' => null,
            'json' => null,
            'technical_drawing' => null,
            'screenshot' => null,
        ];

        $apiKey = config('ai-cad.api.key');

        // Download OBJ
        if (! empty($data['obj_export'])) {
            $files['obj'] = $this->downloadFile($data['obj_export'], $storagePath, 'obj', $apiKey);
        }

        // Download STEP
        if (! empty($data['step_export'])) {
            $files['step'] = $this->downloadFile($data['step_export'], $storagePath, 'step', $apiKey);
        }

        // Download JSON (priority: json_export, fallback: tessellated_export)
        $jsonUrl = $data['json_export'] ?? $data['tessellated_export'] ?? null;
        if ($jsonUrl) {
            $files['json'] = $this->downloadFile($jsonUrl, $storagePath, 'json', $apiKey);
        }

        // Download Technical Drawing
        if (! empty($data['technical_drawing_export'])) {
            $files['technical_drawing'] = $this->downloadFile(
                $data['technical_drawing_export'],
                $storagePath,
                'pdf',
                $apiKey
            );
        }

        // Download Screenshot
        if (! empty($data['screenshot_export'])) {
            $files['screenshot'] = $this->downloadFile($data['screenshot_export'], $storagePath, 'png', $apiKey);
        }

        return $files;
    }

    /**
     * Download a single file and store it
     */
    protected function downloadFile(string $url, string $storagePath, string $extension, string $apiKey): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])->timeout(60)->get($url);

            if (! $response->successful()) {
                $this->warn("   ⚠️  Failed to download {$extension} file");

                return null;
            }

            $filename = uniqid('cad_').'.'.$extension;
            $fullPath = "{$storagePath}/{$filename}";

            Storage::put($fullPath, $response->body());

            return $fullPath;
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Error downloading {$extension}: {$e->getMessage()}");
            Log::warning('[CACHE CMD] File download failed', [
                'url' => $url,
                'extension' => $extension,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse SSE (Server-Sent Events) response to extract final_response
     */
    protected function parseSSEResponse(string $sseBody): ?array
    {
        // Split by lines
        $lines = explode("\n", $sseBody);
        $finalResponse = null;
        $currentStep = null;
        $progressBar = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines or comments
            if (empty($line) || str_starts_with($line, ':')) {
                continue;
            }

            // Parse SSE event (format: "data: {...}")
            if (str_starts_with($line, 'data: ')) {
                $jsonData = substr($line, 6); // Remove "data: " prefix
                $event = json_decode($jsonData, true);

                if ($event === null) {
                    continue; // Skip invalid JSON
                }

                // Update progress display
                if (isset($event['step']) && isset($event['overall_percentage'])) {
                    $step = $event['step'];
                    $percentage = $event['overall_percentage'];

                    // Initialize progress bar on first event
                    if ($progressBar === null) {
                        $progressBar = $this->output->createProgressBar(100);
                        $progressBar->setFormat('   %current%% [%bar%] %message%');
                        $progressBar->start();
                    }

                    // Update progress
                    if ($currentStep !== $step) {
                        $currentStep = $step;
                        $progressBar->setMessage(ucfirst(str_replace('_', ' ', $step)));
                    }
                    $progressBar->setProgress((int) $percentage);
                }

                // Check for final_response
                if (isset($event['final_response'])) {
                    $finalResponse = $event['final_response'];

                    if ($progressBar) {
                        $progressBar->finish();
                        $this->newLine();
                    }

                    break; // Stop parsing, we have what we need
                }
            }
        }

        if ($progressBar && $finalResponse === null) {
            $progressBar->finish();
            $this->newLine();
        }

        if ($finalResponse === null) {
            $this->warn('   ⚠️  No final_response found in SSE stream');

            return null;
        }

        return $finalResponse;
    }
}
