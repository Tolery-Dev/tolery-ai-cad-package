<?php

use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Subscription;
use Stripe\Event as StripeEvent;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Services\AiCadStripe;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
});

function dispatchSubscriptionWebhook(string $type, array $subscription): TestResponse
{
    $stripeEvent = StripeEvent::constructFrom([
        'id' => 'evt_'.bin2hex(random_bytes(4)),
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $subscription],
    ]);

    test()->mock(AiCadStripe::class, function ($mock) use ($stripeEvent) {
        $mock->shouldReceive('verifyWebhookSignature')->andReturn($stripeEvent);
    });

    return test()->postJson(
        route('ai-cad.stripe.webhook'),
        ['data' => ['object' => $subscription]],
        ['Stripe-Signature' => 't=1,v1=fake'],
    );
}

it('marks subscription as ended when customer.subscription.deleted is received', function () {
    $team = ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_cancel_immediate']);
    $sub = $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_cancel_immediate',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
        'ends_at' => null,
    ]);

    $response = dispatchSubscriptionWebhook('customer.subscription.deleted', [
        'id' => 'sub_cancel_immediate',
        'customer' => 'cus_cancel_immediate',
    ]);

    $response->assertOk();

    $sub->refresh();
    expect($sub->stripe_status)->toBe('canceled')
        ->and($sub->ends_at)->not->toBeNull()
        ->and($sub->ends_at->lessThanOrEqualTo(Carbon::now()->addSecond()))->toBeTrue();
});

it('preserves future ends_at on customer.subscription.deleted (natural expiry after grace period)', function () {
    $team = ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_grace_expiry']);
    $futureEnd = Carbon::now()->addDays(10);
    $sub = $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_grace_expiry',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
        'ends_at' => $futureEnd,
    ]);

    $response = dispatchSubscriptionWebhook('customer.subscription.deleted', [
        'id' => 'sub_grace_expiry',
        'customer' => 'cus_grace_expiry',
    ]);

    $response->assertOk();

    $sub->refresh();
    expect($sub->stripe_status)->toBe('canceled')
        ->and($sub->ends_at->toDateString())->toBe($futureEnd->toDateString());
});

it('does not error when customer.subscription.deleted references an unknown subscription', function () {
    $response = dispatchSubscriptionWebhook('customer.subscription.deleted', [
        'id' => 'sub_unknown_to_db',
        'customer' => 'cus_whatever',
    ]);

    $response->assertOk();

    expect(Subscription::where('stripe_id', 'sub_unknown_to_db')->exists())->toBeFalse();
});
