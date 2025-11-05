<?php

use Tolery\AiCad\Models\SubscriptionPrice;
use Tolery\AiCad\Models\SubscriptionProduct;

it('can create a subscription price', function () {
    $product = SubscriptionProduct::factory()->create();

    $price = SubscriptionPrice::factory()->create([
        'subscription_product_id' => $product->id,
        'amount' => 3900,
        'interval' => 'month',
    ]);

    expect($price)->toBeInstanceOf(SubscriptionPrice::class)
        ->and($price->amount)->toBe(3900)
        ->and($price->price)->toBe(39.00)
        ->and($price->interval)->toBe('month')
        ->and($price->active)->toBeTrue();
});

it('belongs to a subscription product', function () {
    $product = SubscriptionProduct::factory()->create();
    $price = SubscriptionPrice::factory()->create([
        'subscription_product_id' => $product->id,
    ]);

    expect($price->product)->toBeInstanceOf(SubscriptionProduct::class)
        ->and($price->product->id)->toBe($product->id);
});

it('can have multiple prices per product', function () {
    $product = SubscriptionProduct::factory()->create();

    $monthlyPrice = SubscriptionPrice::factory()->monthly()->create([
        'subscription_product_id' => $product->id,
        'amount' => 3900,
    ]);

    $yearlyPrice = SubscriptionPrice::factory()->yearly()->create([
        'subscription_product_id' => $product->id,
        'amount' => 39000,
    ]);

    expect($product->prices)->toHaveCount(2)
        ->and($product->activeMonthlyPrice->id)->toBe($monthlyPrice->id)
        ->and($product->activeYearlyPrice->id)->toBe($yearlyPrice->id);
});

it('can scope active prices', function () {
    $product = SubscriptionProduct::factory()->create();

    SubscriptionPrice::factory()->create([
        'subscription_product_id' => $product->id,
        'active' => true,
    ]);

    SubscriptionPrice::factory()->archived()->create([
        'subscription_product_id' => $product->id,
    ]);

    $activePrices = SubscriptionPrice::active()->get();

    expect($activePrices)->toHaveCount(1)
        ->and($activePrices->first()->active)->toBeTrue();
});

it('can archive a price', function () {
    $price = SubscriptionPrice::factory()->create();

    expect($price->isArchived())->toBeFalse();

    $price->archive();

    expect($price->fresh()->isArchived())->toBeTrue()
        ->and($price->fresh()->active)->toBeFalse()
        ->and($price->fresh()->archived_at)->not->toBeNull();
});

it('can scope monthly prices', function () {
    SubscriptionPrice::factory()->monthly()->create();
    SubscriptionPrice::factory()->yearly()->create();

    $monthlyPrices = SubscriptionPrice::monthly()->get();

    expect($monthlyPrices)->toHaveCount(1)
        ->and($monthlyPrices->first()->interval)->toBe('month');
});

it('can scope yearly prices', function () {
    SubscriptionPrice::factory()->monthly()->create();
    SubscriptionPrice::factory()->yearly()->create();

    $yearlyPrices = SubscriptionPrice::yearly()->get();

    expect($yearlyPrices)->toHaveCount(1)
        ->and($yearlyPrices->first()->interval)->toBe('year');
});

it('converts amount from cents to euros correctly', function () {
    $price = SubscriptionPrice::factory()->create(['amount' => 4900]);

    expect($price->price)->toBe(49.00);
});

it('supports price history with grandfathering', function () {
    $product = SubscriptionProduct::factory()->create();

    // Old price (archived)
    $oldPrice = SubscriptionPrice::factory()->create([
        'subscription_product_id' => $product->id,
        'amount' => 3900,
        'stripe_price_id' => 'price_old_123',
        'active' => false,
        'archived_at' => now()->subDays(30),
    ]);

    // New price (active)
    $newPrice = SubscriptionPrice::factory()->create([
        'subscription_product_id' => $product->id,
        'amount' => 4900,
        'stripe_price_id' => 'price_new_456',
        'active' => true,
    ]);

    $allPrices = $product->prices;
    $activePrices = $product->activePrices;

    expect($allPrices)->toHaveCount(2)
        ->and($activePrices)->toHaveCount(1)
        ->and($activePrices->first()->id)->toBe($newPrice->id)
        ->and($activePrices->first()->amount)->toBe(4900);
});
