<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Tolery\AiCad\Services\InvoiceSyncService;

class SyncInvoices extends Command
{
    public $signature = 'ai-cad:sync-invoices';

    public $description = 'Backfill local invoice records from Stripe for every team';

    public function handle(InvoiceSyncService $invoiceSync): int
    {
        $this->info('🔄 Syncing invoices from Stripe...');

        try {
            $result = $invoiceSync->syncAllFromStripe();

            $this->info("✅ Synced {$result['synced']} invoices");

            if ($result['skipped'] > 0) {
                $this->warn("⚠️  Skipped {$result['skipped']} invoices (no matching team)");
            }

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
