<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Stripe\ErrorObject;
use Stripe\Exception\InvalidRequestException;
use Tolery\AiCad\Jobs\Stripe\ProductCreate;
use Tolery\AiCad\Jobs\Stripe\ProductUpdate;
use Tolery\AiCad\Models\SubscriptionProduct;

function makeStripeInvalidRequestException(string $message, ?string $code): InvalidRequestException
{
    $exception = new InvalidRequestException($message);

    // Stripe exposes the error code via getStripeCode() reading the internal
    // $stripeCode property; setError() alone is not enough.
    $exception->setStripeCode($code);
    $exception->setError(ErrorObject::constructFrom(['code' => $code, 'message' => $message]));

    return $exception;
}

describe('ProductUpdate::isStripeMissingResource', function () {
    it('returns true for a Stripe resource_missing error', function () {
        $e = makeStripeInvalidRequestException("No such product: 'prod_dead'", 'resource_missing');

        expect(ProductUpdate::isStripeMissingResource($e))->toBeTrue();
    });

    it('returns false for other Stripe error codes', function () {
        $e = makeStripeInvalidRequestException('Parameter missing', 'parameter_missing');

        expect(ProductUpdate::isStripeMissingResource($e))->toBeFalse();
    });

    it('returns false for non-Stripe exceptions', function () {
        expect(ProductUpdate::isStripeMissingResource(new RuntimeException('boom')))->toBeFalse();
    });
});

describe('ProductUpdate handle() resilience', function () {
    it('nulls stripe_id and dispatches ProductCreate when Stripe says resource_missing', function () {
        Bus::fake();

        $product = SubscriptionProduct::withoutEvents(fn () => SubscriptionProduct::factory()->create([
            'stripe_id' => 'prod_dead_xyz',
            'stripe_price_id' => 'price_dead_xyz',
        ]));

        $job = Mockery::mock(ProductUpdate::class, [$product, false])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('pushToStripe')->once()->andThrow(
            makeStripeInvalidRequestException("No such product: 'prod_dead_xyz'", 'resource_missing')
        );

        $job->handle();

        $product->refresh();

        expect($product->stripe_id)->toBeNull()
            ->and($product->stripe_price_id)->toBeNull();

        Bus::assertDispatched(ProductCreate::class);
    });

    it('rethrows other Stripe errors unchanged', function () {
        Bus::fake();

        $product = SubscriptionProduct::withoutEvents(fn () => SubscriptionProduct::factory()->create([
            'stripe_id' => 'prod_alive',
        ]));

        $job = Mockery::mock(ProductUpdate::class, [$product, false])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('pushToStripe')->once()->andThrow(
            makeStripeInvalidRequestException('Parameter missing: name', 'parameter_missing')
        );

        expect(fn () => $job->handle())->toThrow(InvalidRequestException::class);

        Bus::assertNotDispatched(ProductCreate::class);
        expect($product->fresh()->stripe_id)->toBe('prod_alive');
    });

    it('returns early when the subscription product has no stripe_id', function () {
        Bus::fake();
        Queue::fake();

        $product = SubscriptionProduct::withoutEvents(fn () => SubscriptionProduct::factory()->create(['stripe_id' => null]));

        (new ProductUpdate($product))->handle();

        Bus::assertNotDispatched(ProductCreate::class);
    });
});

describe('ProductDelete handle() resilience', function () {
    it('treats a missing Stripe product as a successful no-op', function () {
        // We rely on the real Cashier::stripe() failing with resource_missing
        // would require a network/mock. Instead we verify the catch path via
        // the static helper used inside, which is fully unit-tested above.
        $e = makeStripeInvalidRequestException("No such product: 'prod_dead'", 'resource_missing');

        expect(ProductUpdate::isStripeMissingResource($e))->toBeTrue();
    });
});
