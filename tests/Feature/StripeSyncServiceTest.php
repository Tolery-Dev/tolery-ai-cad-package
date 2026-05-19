<?php

use Stripe\Product;
use Stripe\StripeClient;
use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\AiCadStripe;
use Tolery\AiCad\Services\StripeSyncService;

/**
 * Helper to invoke the protected syncProduct method via reflection.
 */
function invokeSyncProduct(StripeSyncService $service, Product $stripeProduct): SubscriptionProduct
{
    $reflection = new ReflectionMethod($service, 'syncProduct');
    $reflection->setAccessible(true);

    return $reflection->invoke($service, $stripeProduct);
}

function makeStripeProduct(array $overrides = []): Product
{
    return Product::constructFrom(array_merge([
        'id' => 'prod_test_'.bin2hex(random_bytes(4)),
        'object' => 'product',
        'name' => 'Pro',
        'description' => 'Plan Pro',
        'active' => true,
        'metadata' => ['files_allowed' => '100'],
    ], $overrides));
}

beforeEach(function () {
    $this->stripeMock = Mockery::mock(StripeClient::class);
    $this->aiCadStripe = Mockery::mock(AiCadStripe::class);
    $this->aiCadStripe->shouldReceive('client')->andReturn($this->stripeMock);
});

it('sets frequency to MONTHLY by default when creating a new product', function () {
    $service = new StripeSyncService($this->aiCadStripe);
    $stripeProduct = makeStripeProduct();

    $product = invokeSyncProduct($service, $stripeProduct);

    expect($product->frequency)->toBe(ResetFrequency::MONTHLY);
});

it('uses Stripe metadata frequency when provided', function () {
    $service = new StripeSyncService($this->aiCadStripe);
    $stripeProduct = makeStripeProduct([
        'metadata' => ['files_allowed' => '100', 'frequency' => 'yearly'],
    ]);

    $product = invokeSyncProduct($service, $stripeProduct);

    expect($product->frequency)->toBe(ResetFrequency::YEARLY);
});

it('preserves an existing non-null frequency on update', function () {
    $existing = SubscriptionProduct::factory()->create([
        'stripe_id' => 'prod_existing_yearly',
        'frequency' => ResetFrequency::YEARLY,
    ]);

    $service = new StripeSyncService($this->aiCadStripe);
    $stripeProduct = makeStripeProduct(['id' => 'prod_existing_yearly']);

    $product = invokeSyncProduct($service, $stripeProduct);

    expect($product->id)->toBe($existing->id)
        ->and($product->frequency)->toBe(ResetFrequency::YEARLY);
});

it('heals an existing product whose frequency is NULL', function () {
    $existing = SubscriptionProduct::factory()->create([
        'stripe_id' => 'prod_existing_null',
        'frequency' => null,
    ]);

    $service = new StripeSyncService($this->aiCadStripe);
    $stripeProduct = makeStripeProduct(['id' => 'prod_existing_null']);

    $product = invokeSyncProduct($service, $stripeProduct);

    expect($product->id)->toBe($existing->id)
        ->and($product->frequency)->toBe(ResetFrequency::MONTHLY);
});

it('syncs the product image URL from Stripe', function () {
    $service = new StripeSyncService($this->aiCadStripe);
    $stripeProduct = makeStripeProduct([
        'images' => ['https://files.stripe.com/product-image.png'],
    ]);

    $product = invokeSyncProduct($service, $stripeProduct);

    expect($product->image_url)->toBe('https://files.stripe.com/product-image.png');
});

it('sets the image URL to null when the Stripe product has no image', function () {
    $service = new StripeSyncService($this->aiCadStripe);

    $product = invokeSyncProduct($service, makeStripeProduct(['images' => []]));

    expect($product->image_url)->toBeNull();
});

it('updates basic attributes from Stripe payload', function () {
    SubscriptionProduct::factory()->create([
        'stripe_id' => 'prod_update_attrs',
        'name' => 'Old Name',
        'frequency' => ResetFrequency::MONTHLY,
    ]);

    $service = new StripeSyncService($this->aiCadStripe);
    $stripeProduct = makeStripeProduct([
        'id' => 'prod_update_attrs',
        'name' => 'New Name',
        'description' => 'Updated description',
        'active' => false,
        'metadata' => ['files_allowed' => '50'],
    ]);

    $product = invokeSyncProduct($service, $stripeProduct);

    expect($product->name)->toBe('New Name')
        ->and($product->description)->toBe('Updated description')
        ->and($product->active)->toBeFalse()
        ->and($product->files_allowed)->toBe(50)
        ->and($product->frequency)->toBe(ResetFrequency::MONTHLY);
});
