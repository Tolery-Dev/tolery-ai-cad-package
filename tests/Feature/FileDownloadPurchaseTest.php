<?php

use Illuminate\Support\Facades\Http;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\FileAccessService;

beforeEach(function () {
    SubscriptionProduct::create([
        'stripe_id' => 'prod_test_oneshot',
        'name' => 'One Shot',
        'description' => 'Achat unique',
        'files_allowed' => 1,
        'active' => true,
    ]);

    Http::fake([
        'https://api.stripe.com/v1/prices*' => Http::response([
            'data' => [
                [
                    'id' => 'price_test_oneshot',
                    'unit_amount' => 999,
                    'currency' => 'eur',
                    'active' => true,
                ],
            ],
        ], 200),
    ]);
});

describe('FileAccessService - Download Status', function () {
    it('allows download for subscribed user with quota', function () {
        // Create a subscription product first
        $product = SubscriptionProduct::create([
            'stripe_id' => 'prod_test_monthly',
            'name' => 'Monthly Plan',
            'description' => 'Monthly subscription',
            'files_allowed' => 10,
            'active' => true,
        ]);

        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        $team->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $team->limits()->create([
            'subscription_product_id' => $product->id,
            'used_amount' => 3,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
        ]);

        $service = app(FileAccessService::class);
        $status = $service->canDownloadChat($team, $chat);

        expect($status['can_download'])->toBeTrue()
            ->and($status['reason'])->toBe('quota_available')
            ->and($status['remaining_quota'])->toBe(7)
            ->and($status['total_quota'])->toBe(10);
    });

    it('denies download for subscribed user with exceeded quota', function () {
        // Create a subscription product first
        $product = SubscriptionProduct::create([
            'stripe_id' => 'prod_test_monthly',
            'name' => 'Monthly Plan',
            'description' => 'Monthly subscription',
            'files_allowed' => 10,
            'active' => true,
        ]);

        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        $team->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $team->limits()->create([
            'subscription_product_id' => $product->id,
            'used_amount' => 10,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
        ]);

        $service = app(FileAccessService::class);
        $status = $service->canDownloadChat($team, $chat);

        expect($status['can_download'])->toBeFalse()
            ->and($status['reason'])->toBe('quota_exceeded')
            ->and($status['remaining_quota'])->toBe(0)
            ->and($status['options']['can_purchase'])->toBeTrue()
            ->and($status['options']['can_subscribe'])->toBeFalse();
    });

    it('allows download if file was previously purchased', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        FilePurchase::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'stripe_payment_intent_id' => 'pi_test',
            'amount' => 999,
            'currency' => 'eur',
            'purchased_at' => now(),
        ]);

        $service = app(FileAccessService::class);
        $status = $service->canDownloadChat($team, $chat);

        expect($status['can_download'])->toBeTrue()
            ->and($status['reason'])->toBe('purchased');
    });

    it('denies download for non-subscribed user without purchase', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        $service = app(FileAccessService::class);
        $status = $service->canDownloadChat($team, $chat);

        expect($status['can_download'])->toBeFalse()
            ->and($status['reason'])->toBe('no_subscription')
            ->and($status['options']['can_purchase'])->toBeTrue()
            ->and($status['options']['can_subscribe'])->toBeTrue();
    });
});

describe('FileAccessService - One-Shot Pricing', function () {
    it('retrieves one-shot price from Stripe API', function () {
        $service = app(FileAccessService::class);
        $price = $service->getOneTimePurchasePrice();

        expect($price)->toBe(999);
    });

    it('returns default price if Stripe API fails', function () {
        Http::fake([
            'https://api.stripe.com/v1/prices*' => Http::response([], 500),
        ]);

        $service = app(FileAccessService::class);
        $price = $service->getOneTimePurchasePrice();

        expect($price)->toBe(config('ai-cad.file_purchase_price', 999));
    });

    it('returns default price if no one-shot product exists', function () {
        SubscriptionProduct::where('files_allowed', 1)->delete();

        $service = app(FileAccessService::class);
        $price = $service->getOneTimePurchasePrice();

        expect($price)->toBe(config('ai-cad.file_purchase_price', 999));
    });
});

describe('FilePurchase - Webhook Integration', function () {
    it('creates file purchase record from webhook payload', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_webhook',
                    'amount' => 999,
                    'currency' => 'eur',
                    'metadata' => [
                        'type' => 'file_purchase',
                        'team_id' => (string) $team->id,
                        'chat_id' => (string) $chat->id,
                    ],
                ],
            ],
        ];

        $controller = new \Tolery\AiCad\Http\Controllers\StripeWebhookController;
        $response = $controller->handlePaymentIntentSucceeded($payload);

        expect($response->getStatusCode())->toBe(200);
        expect(FilePurchase::count())->toBe(1);

        $purchase = FilePurchase::first();
        expect($purchase->team_id)->toBe($team->id)
            ->and($purchase->chat_id)->toBe($chat->id)
            ->and($purchase->stripe_payment_intent_id)->toBe('pi_test_webhook')
            ->and($purchase->amount)->toBe(999)
            ->and($purchase->currency)->toBe('eur');
    });

    it('prevents duplicate purchases with same payment intent', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        FilePurchase::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'stripe_payment_intent_id' => 'pi_test_duplicate',
            'amount' => 999,
            'currency' => 'eur',
            'purchased_at' => now(),
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_duplicate',
                    'amount' => 999,
                    'currency' => 'eur',
                    'metadata' => [
                        'type' => 'file_purchase',
                        'team_id' => (string) $team->id,
                        'chat_id' => (string) $chat->id,
                    ],
                ],
            ],
        ];

        $controller = new \Tolery\AiCad\Http\Controllers\StripeWebhookController;
        $response = $controller->handlePaymentIntentSucceeded($payload);

        expect($response->getStatusCode())->toBe(200);
        expect(FilePurchase::count())->toBe(1);
    });

    it('ignores webhook if type is not file_purchase', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_subscription',
                    'amount' => 2999,
                    'currency' => 'eur',
                    'metadata' => [
                        'type' => 'subscription_payment',
                        'team_id' => (string) $team->id,
                    ],
                ],
            ],
        ];

        $controller = new \Tolery\AiCad\Http\Controllers\StripeWebhookController;
        $response = $controller->handlePaymentIntentSucceeded($payload);

        expect($response->getStatusCode())->toBe(200);
        expect(FilePurchase::count())->toBe(0);
    });
});
