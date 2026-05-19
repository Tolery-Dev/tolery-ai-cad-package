<?php

use Stripe\Collection as StripeCollection;
use Stripe\Product as StripeProduct;
use Tolery\AiCad\Livewire\Admin\SubscriptionProductTable;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\AiCadStripe;

it('returns a Stripe error message when the catalogue cannot be loaded', function () {
    $mock = Mockery::mock(AiCadStripe::class);
    $mock->shouldReceive('listProducts')->andThrow(new Exception('Stripe unavailable'));
    app()->instance(AiCadStripe::class, $mock);

    $catalog = (new SubscriptionProductTable)->catalog();

    expect($catalog['error'])->toBe('Stripe unavailable')
        ->and($catalog['products'])->toBe([]);
});

it('pairs Stripe products with their local sync state', function () {
    SubscriptionProduct::factory()->create([
        'stripe_id' => 'prod_synced',
        'name' => 'Plan Pro',
        'description' => 'Le plan Pro',
        'active' => true,
        'image_url' => null,
    ]);

    $mock = Mockery::mock(AiCadStripe::class);
    $mock->shouldReceive('listProducts')->andReturn(
        StripeCollection::constructFrom(['data' => [
            StripeProduct::constructFrom([
                'id' => 'prod_synced',
                'name' => 'Plan Pro',
                'description' => 'Le plan Pro',
                'active' => true,
                'images' => [],
            ]),
            StripeProduct::constructFrom([
                'id' => 'prod_new',
                'name' => 'Plan New',
                'description' => null,
                'active' => true,
                'images' => ['https://files.stripe.com/new.png'],
            ]),
        ]])
    );
    $mock->shouldReceive('listPrices')->andReturn(StripeCollection::constructFrom(['data' => []]));
    app()->instance(AiCadStripe::class, $mock);

    $catalog = (new SubscriptionProductTable)->catalog();

    expect($catalog['error'])->toBeNull()
        ->and($catalog['products'])->toHaveCount(2);

    $synced = collect($catalog['products'])->firstWhere('id', 'prod_synced');
    $new = collect($catalog['products'])->firstWhere('id', 'prod_new');

    expect($synced['synced'])->toBeTrue()
        ->and($synced['out_of_sync'])->toBeFalse()
        ->and($new['synced'])->toBeFalse()
        ->and($new['image'])->toBe('https://files.stripe.com/new.png');
});

it('flags a locally synced product as out of sync when Stripe data changed', function () {
    SubscriptionProduct::factory()->create([
        'stripe_id' => 'prod_drift',
        'name' => 'Ancien nom',
        'description' => 'Ancienne description',
        'active' => true,
        'image_url' => null,
    ]);

    $mock = Mockery::mock(AiCadStripe::class);
    $mock->shouldReceive('listProducts')->andReturn(
        StripeCollection::constructFrom(['data' => [
            StripeProduct::constructFrom([
                'id' => 'prod_drift',
                'name' => 'Nouveau nom',
                'description' => 'Ancienne description',
                'active' => true,
                'images' => [],
            ]),
        ]])
    );
    $mock->shouldReceive('listPrices')->andReturn(StripeCollection::constructFrom(['data' => []]));
    app()->instance(AiCadStripe::class, $mock);

    $catalog = (new SubscriptionProductTable)->catalog();

    expect($catalog['products'][0]['synced'])->toBeTrue()
        ->and($catalog['products'][0]['out_of_sync'])->toBeTrue();
});
