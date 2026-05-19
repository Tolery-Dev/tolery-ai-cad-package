<?php

use Stripe\Event as StripeEvent;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\Invoice;
use Tolery\AiCad\Services\AiCadStripe;
use Tolery\AiCad\Services\InvoiceSyncService;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
});

function invoicePayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'in_'.bin2hex(random_bytes(4)),
        'object' => 'invoice',
        'customer' => 'cus_sub_team',
        'subscription' => 'sub_test',
        'payment_intent' => 'pi_sub',
        'number' => 'TOLERY-0001',
        'status' => 'paid',
        'subtotal' => 5000,
        'tax' => 1000,
        'total' => 6000,
        'amount_paid' => 6000,
        'currency' => 'eur',
        'hosted_invoice_url' => 'https://stripe.test/invoice/abc',
        'invoice_pdf' => 'https://stripe.test/invoice/abc.pdf',
        'period_start' => now()->startOfMonth()->timestamp,
        'period_end' => now()->endOfMonth()->timestamp,
        'created' => now()->timestamp,
        'status_transitions' => ['paid_at' => now()->timestamp],
        'metadata' => [],
    ], $overrides);
}

it('mirrors a subscription invoice into the local table', function () {
    $team = ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_sub_team']);

    $invoice = app(InvoiceSyncService::class)->syncInvoice(invoicePayload());

    expect($invoice)->not->toBeNull()
        ->and($invoice->team_id)->toBe($team->id)
        ->and($invoice->type)->toBe('subscription')
        ->and($invoice->subtotal)->toBe(5000)
        ->and($invoice->tax)->toBe(1000)
        ->and($invoice->total)->toBe(6000)
        ->and($invoice->number)->toBe('TOLERY-0001');

    expect($team->invoices()->count())->toBe(1);
});

it('is idempotent when the same invoice is synced twice', function () {
    ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_sub_team']);

    $payload = invoicePayload(['id' => 'in_fixed', 'status' => 'open']);
    app(InvoiceSyncService::class)->syncInvoice($payload);

    $payload['status'] = 'paid';
    app(InvoiceSyncService::class)->syncInvoice($payload);

    expect(Invoice::count())->toBe(1)
        ->and(Invoice::first()->status)->toBe('paid');
});

it('creates the invoice and the file purchase for a one-shot invoice', function () {
    $team = ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_oneshot']);
    $chat = Chat::factory()->create(['team_id' => $team->id]);

    $invoice = app(InvoiceSyncService::class)->syncInvoice(invoicePayload([
        'id' => 'in_oneshot',
        'customer' => 'cus_oneshot',
        'subscription' => null,
        'payment_intent' => 'pi_oneshot',
        'metadata' => [
            'type' => 'file_purchase',
            'team_id' => (string) $team->id,
            'chat_id' => (string) $chat->id,
        ],
    ]));

    expect($invoice->type)->toBe('one_shot')
        ->and(FilePurchase::count())->toBe(1);

    $purchase = FilePurchase::first();
    expect($purchase->stripe_payment_intent_id)->toBe('pi_oneshot')
        ->and($purchase->chat_id)->toBe($chat->id)
        ->and($invoice->fresh()->file_purchase_id)->toBe($purchase->id);
});

it('links an already-existing file purchase without duplicating it', function () {
    $team = ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_oneshot']);
    $chat = Chat::factory()->create(['team_id' => $team->id]);

    FilePurchase::create([
        'team_id' => $team->id,
        'chat_id' => $chat->id,
        'stripe_payment_intent_id' => 'pi_oneshot',
        'amount' => 1199,
        'currency' => 'eur',
        'purchased_at' => now(),
    ]);

    $invoice = app(InvoiceSyncService::class)->syncInvoice(invoicePayload([
        'id' => 'in_oneshot2',
        'customer' => 'cus_oneshot',
        'subscription' => null,
        'payment_intent' => 'pi_oneshot',
        'metadata' => [
            'type' => 'file_purchase',
            'team_id' => (string) $team->id,
            'chat_id' => (string) $chat->id,
        ],
    ]));

    expect(FilePurchase::count())->toBe(1)
        ->and($invoice->fresh()->file_purchase_id)->toBe(FilePurchase::first()->id);
});

it('skips an invoice when no team matches', function () {
    $invoice = app(InvoiceSyncService::class)->syncInvoice(invoicePayload([
        'customer' => 'cus_unknown',
        'metadata' => [],
    ]));

    expect($invoice)->toBeNull()
        ->and(Invoice::count())->toBe(0);
});

it('handles the invoice.paid webhook end to end', function () {
    ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_sub_team']);

    $payload = invoicePayload(['id' => 'in_webhook']);

    $stripeEvent = StripeEvent::constructFrom([
        'id' => 'evt_invoice_paid',
        'object' => 'event',
        'type' => 'invoice.paid',
        'data' => ['object' => $payload],
    ]);

    test()->mock(AiCadStripe::class, function ($mock) use ($stripeEvent) {
        $mock->shouldReceive('verifyWebhookSignature')->andReturn($stripeEvent);
    });

    $response = test()->postJson(
        route('ai-cad.stripe.webhook'),
        ['data' => ['object' => $payload]],
        ['Stripe-Signature' => 't=1,v1=fake'],
    );

    $response->assertOk();

    expect(Invoice::where('stripe_invoice_id', 'in_webhook')->exists())->toBeTrue();
});
