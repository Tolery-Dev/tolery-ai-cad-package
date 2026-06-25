<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Stripe\Exception\ApiErrorException;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Services\AiCadStripe;

class BackfillPurchaseHt extends Command
{
    public $signature = 'ai-cad:backfill-purchase-ht {--dry-run : Affiche les changements sans rien écrire}';

    public $description = 'Recalcule le montant HT des achats à la pièce (amount_total - TVA) depuis Stripe';

    public function handle(AiCadStripe $aiCadStripe): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /** @var Collection<int, FilePurchase> $purchases */
        $purchases = FilePurchase::query()->orderBy('id')->get();

        if ($purchases->isEmpty()) {
            $this->info('Aucun achat à traiter.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $rows = [];

        foreach ($purchases as $purchase) {
            $paymentIntentId = $purchase->stripe_payment_intent_id;

            if ($paymentIntentId === '') {
                $rows[] = [$purchase->id, $purchase->amount, '—', 'skip (pas de payment_intent)'];
                $skipped++;

                continue;
            }

            try {
                $sessions = $aiCadStripe->client()->checkout->sessions->all([
                    'payment_intent' => $paymentIntentId,
                    'limit' => 1,
                ]);
            } catch (ApiErrorException $e) {
                $rows[] = [$purchase->id, $purchase->amount, '—', 'erreur Stripe: '.$e->getMessage()];
                $skipped++;

                continue;
            }

            $session = $sessions->data[0] ?? null;

            if ($session === null) {
                $rows[] = [$purchase->id, $purchase->amount, '—', 'skip (session introuvable)'];
                $skipped++;

                continue;
            }

            $amountTotal = (int) ($session->amount_total ?? 0);
            $amountTax = (int) ($session->total_details->amount_tax ?? 0);
            $amountHt = $amountTotal - $amountTax;

            if ($amountHt === (int) $purchase->amount) {
                $rows[] = [$purchase->id, $purchase->amount, $amountHt, 'inchangé'];

                continue;
            }

            $rows[] = [$purchase->id, $purchase->amount, $amountHt, $dryRun ? 'à corriger' : 'corrigé'];

            if (! $dryRun) {
                $purchase->update(['amount' => $amountHt]);
                $updated++;
            }
        }

        $this->table(['ID', 'Avant (cts)', 'HT (cts)', 'Statut'], $rows);

        if ($dryRun) {
            $this->warn('Dry-run : aucune écriture effectuée.');
        } else {
            $this->info("✅ {$updated} achat(s) corrigé(s), {$skipped} ignoré(s).");
        }

        return self::SUCCESS;
    }
}
