<?php

use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Models\SubscriptionProduct;

it('returns a valid stripe object when frequency is set', function () {
    $product = SubscriptionProduct::factory()->create([
        'frequency' => ResetFrequency::MONTHLY,
    ]);

    $stripeObject = $product->toStripeObject();

    expect($stripeObject['metadata']['frequency'])->toBe('monthly');
});

it('returns a valid stripe object when frequency is null', function () {
    $product = SubscriptionProduct::factory()->create([
        'frequency' => null,
    ]);

    $stripeObject = $product->toStripeObject();

    expect($stripeObject['metadata']['frequency'])->toBe('')
        ->and($stripeObject['name'])->toBe($product->name)
        ->and($stripeObject['metadata']['laravel_product_id'])->toBe((string) $product->id);
});

it('returns a valid stripe price object when frequency is null', function () {
    $product = SubscriptionProduct::factory()->create([
        'frequency' => null,
        'stripe_id' => 'prod_test123',
    ]);

    $stripePriceObject = $product->toStripePriceObject();

    expect($stripePriceObject['recurring']['interval'])->toBe('month')
        ->and($stripePriceObject['product'])->toBe('prod_test123');
});
