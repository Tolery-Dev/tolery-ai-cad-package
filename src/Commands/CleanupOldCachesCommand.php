<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Tolery\AiCad\Services\PredefinedPromptCacheService;

class CleanupOldCachesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai-cad:cleanup-cache
                            {--days=30 : Delete caches older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old cached prompts and their files';

    /**
     * Execute the console command.
     */
    public function handle(PredefinedPromptCacheService $cacheService): int
    {
        $retentionDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("🧹 Cleaning up caches older than {$retentionDays} days...");
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be deleted');
            $this->newLine();
        }

        // Get statistics before cleanup
        $stats = $cacheService->getStatistics();
        $this->info('📊 Current cache statistics:');
        $this->line("   Total caches: {$stats['total_caches']}");
        $this->line("   Total hits: {$stats['total_hits']}");
        $this->newLine();

        if ($dryRun) {
            // Show what would be deleted
            $oldCaches = \Tolery\AiCad\Models\PredefinedPromptCache::olderThan($retentionDays)->get();

            if ($oldCaches->isEmpty()) {
                $this->info('✨ No caches to clean up!');

                return self::SUCCESS;
            }

            $this->warn("Would delete {$oldCaches->count()} cache(s):");
            $this->newLine();

            foreach ($oldCaches as $cache) {
                $age = now()->diffInDays($cache->generated_at);
                $this->line("   • {$cache->prompt_hash} (Age: {$age} days, Hits: {$cache->hits_count})");
                $this->line('     Prompt: '.substr($cache->prompt_text, 0, 80).'...');
            }

            $this->newLine();
            $this->info('💡 Run without --dry-run to actually delete these caches');

            return self::SUCCESS;
        }

        // Actually delete
        $deletedCount = $cacheService->cleanupOldCaches($retentionDays);

        if ($deletedCount === 0) {
            $this->info('✨ No caches to clean up!');
        } else {
            $this->info("✅ Successfully deleted {$deletedCount} old cache(s) and their files");
        }

        // Show updated statistics
        $this->newLine();
        $statsAfter = $cacheService->getStatistics();
        $this->info('📊 Updated cache statistics:');
        $this->line("   Total caches: {$statsAfter['total_caches']} (was {$stats['total_caches']})");
        $this->line("   Total hits: {$statsAfter['total_hits']}");

        return self::SUCCESS;
    }
}
