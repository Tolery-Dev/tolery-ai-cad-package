<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Tolery\AiCad\Services\StripeSyncService;

class SyncStripeProducts extends Command
{
    public $signature = 'ai-cad:sync-stripe
                        {--archive : Also archive prices that are inactive in Stripe}';

    public $description = 'Sync subscription products and prices from Stripe';

    public function handle(StripeSyncService $syncService): int
    {
        $this->info('🔄 Syncing products and prices from Stripe...');
        $this->newLine();

        try {
            $result = $syncService->syncProductsFromStripe();

            $this->info("✅ Synced {$result['products']} products");
            $this->info("✅ Synced {$result['prices']} prices");

            if (! empty($result['deleted_products'])) {
                $this->info("🗑️  Deleted {$result['deleted_products']} orphan products");
            }

            if (! empty($result['errors'])) {
                $this->newLine();
                $this->warn('⚠️  Errors encountered:');
                foreach ($result['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }

            if ($this->option('archive')) {
                $this->newLine();
                $this->info('🗄️  Archiving inactive prices...');

                $archived = $syncService->archiveOldPrices();
                $this->info("✅ Archived {$archived} prices");
            }

            $this->newLine();
            $this->info('✨ Sync completed successfully!');

            return self::SUCCESS;
        } catch (ApiErrorException $e) {
            $this->error('❌ Stripe API Error: '.$e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
