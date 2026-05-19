<?php

namespace Tolery\AiCad\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\Invoice;

/**
 * Mirrors Stripe invoices into the local `invoices` table.
 *
 * Invoices themselves are issued and stored by Stripe; this service keeps a local
 * reference (number, amounts, hosted URL, PDF link) so the application can list them
 * without calling the Stripe API on every request. Customers still consult and download
 * the actual documents through the Stripe billing portal.
 */
class InvoiceSyncService
{
    public function __construct(
        protected AiCadStripe $stripe
    ) {}

    /**
     * Create or update the local record for a single Stripe invoice (array payload).
     *
     * For one-shot file purchases, also ensures the matching FilePurchase exists so
     * the result is identical whether `invoice.paid` or `checkout.session.completed`
     * is processed first.
     */
    public function syncInvoice(array $invoice): ?Invoice
    {
        $stripeInvoiceId = $invoice['id'] ?? null;

        if (! $stripeInvoiceId) {
            return null;
        }

        $team = $this->resolveTeam($invoice);

        if (! $team) {
            Log::warning('[AICAD Invoice] Team not found for invoice', [
                'invoice_id' => $stripeInvoiceId,
                'customer' => $this->idOf($invoice['customer'] ?? null),
            ]);

            return null;
        }

        return DB::transaction(function () use ($invoice, $stripeInvoiceId, $team) {
            $record = Invoice::updateOrCreate(
                ['stripe_invoice_id' => $stripeInvoiceId],
                $this->mapAttributes($invoice, $team),
            );

            if (($invoice['metadata']['type'] ?? null) === 'file_purchase') {
                $this->syncFilePurchase($invoice, $record);
            }

            return $record;
        });
    }

    /**
     * Backfill local invoice records from Stripe for every team with a customer ID.
     *
     * @return array{synced: int, skipped: int}
     */
    public function syncAllFromStripe(): array
    {
        /** @var class-string<ChatTeam> $teamModel */
        $teamModel = config('ai-cad.chat_team_model', ChatTeam::class);

        $synced = 0;
        $skipped = 0;

        $teamModel::whereNotNull('tolerycad_stripe_id')
            ->each(function ($team) use (&$synced, &$skipped) {
                $invoices = $this->stripe->listInvoices($team->tolerycad_stripe_id);

                foreach ($invoices->data as $invoice) {
                    if ($this->syncInvoice($invoice->toArray())) {
                        $synced++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    protected function resolveTeam(array $invoice): ?ChatTeam
    {
        /** @var class-string<ChatTeam> $teamModel */
        $teamModel = config('ai-cad.chat_team_model', ChatTeam::class);

        $teamId = $invoice['metadata']['team_id'] ?? null;
        if ($teamId) {
            $team = $teamModel::find($teamId);
            if ($team) {
                return $team;
            }
        }

        $customerId = $this->idOf($invoice['customer'] ?? null);
        if ($customerId) {
            return $teamModel::where('tolerycad_stripe_id', $customerId)->first();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAttributes(array $invoice, ChatTeam $team): array
    {
        return [
            'team_id' => $team->id,
            'stripe_subscription_id' => $this->idOf($invoice['subscription'] ?? null),
            'stripe_payment_intent_id' => $this->idOf($invoice['payment_intent'] ?? null),
            'number' => $invoice['number'] ?? null,
            'status' => $invoice['status'] ?? null,
            'subtotal' => $invoice['subtotal'] ?? 0,
            'tax' => $invoice['tax'] ?? 0,
            'total' => $invoice['total'] ?? 0,
            'amount_paid' => $invoice['amount_paid'] ?? 0,
            'currency' => $invoice['currency'] ?? 'eur',
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'period_start' => $this->timestamp($invoice['period_start'] ?? null),
            'period_end' => $this->timestamp($invoice['period_end'] ?? null),
            'issued_at' => $this->timestamp($invoice['created'] ?? null),
            'paid_at' => $this->timestamp($invoice['status_transitions']['paid_at'] ?? null),
        ];
    }

    /**
     * Ensure the FilePurchase tied to a one-shot invoice exists and is linked back.
     */
    protected function syncFilePurchase(array $invoice, Invoice $record): void
    {
        $chatId = $invoice['metadata']['chat_id'] ?? null;
        $paymentIntentId = $this->idOf($invoice['payment_intent'] ?? null);

        if (! $chatId || ! $paymentIntentId) {
            return;
        }

        $chat = Chat::find($chatId);

        if (! $chat) {
            Log::error('[AICAD Invoice] Chat not found for file purchase invoice', [
                'chat_id' => $chatId,
                'invoice_id' => $invoice['id'] ?? null,
            ]);

            return;
        }

        $purchase = FilePurchase::firstOrCreate(
            ['stripe_payment_intent_id' => $paymentIntentId],
            [
                'team_id' => $record->team_id,
                'chat_id' => $chat->id,
                'amount' => $invoice['amount_paid'] ?? $invoice['total'] ?? 0,
                'currency' => $invoice['currency'] ?? 'eur',
                'purchased_at' => now(),
            ],
        );

        if ($record->file_purchase_id !== $purchase->id) {
            $record->update(['file_purchase_id' => $purchase->id]);
        }
    }

    protected function timestamp(?int $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    /**
     * Stripe fields can be either a bare ID string or an expanded object — normalise to an ID.
     */
    protected function idOf(mixed $value): ?string
    {
        if (is_array($value)) {
            return $value['id'] ?? null;
        }

        return $value ?: null;
    }
}
