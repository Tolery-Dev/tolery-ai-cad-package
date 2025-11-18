<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RegeneratePredefinedCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour for all 4 prompts

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[CACHE JOB] Starting weekly cache regeneration');

        try {
            // Call the command with --force to regenerate existing caches
            $exitCode = Artisan::call('ai-cad:cache-prompts', [
                '--force' => true,
            ]);

            if ($exitCode === 0) {
                Log::info('[CACHE JOB] Cache regeneration completed successfully');
            } else {
                Log::warning('[CACHE JOB] Cache regeneration completed with warnings', [
                    'exit_code' => $exitCode,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[CACHE JOB] Cache regeneration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[CACHE JOB] Job failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
