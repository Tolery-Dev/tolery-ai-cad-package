<?php

namespace Tolery\AiCad\Database\Seeders;

use Illuminate\Database\Seeder;
use Tolery\AiCad\Services\StripeSyncService;

class SubscriptionProductSeeder extends Seeder
{
    public function run(): void
    {
        $syncService = app(StripeSyncService::class);

        $this->command->info('🔄 Importing subscription products from Stripe...');

        try {
            $result = $syncService->syncProductsFromStripe();

            $this->command->info("✅ Imported {$result['products']} products");
            $this->command->info("✅ Imported {$result['prices']} prices");

            if (! empty($result['errors'])) {
                $this->command->warn('⚠️  Some errors occurred:');
                foreach ($result['errors'] as $error) {
                    $this->command->error("  - {$error}");
                }
            }
        } catch (\Exception $e) {
            $this->command->error("❌ Failed to sync from Stripe: {$e->getMessage()}");
        }
    }
}
