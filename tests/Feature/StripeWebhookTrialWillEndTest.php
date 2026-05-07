<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Stripe\Event as StripeEvent;
use Tolery\AiCad\Events\TrialWillEnd;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Services\AiCadStripe;

beforeEach(function () {
    Event::fake();
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
});

function dispatchTrialWillEndWebhook(array $subscription): TestResponse
{
    $stripeEvent = StripeEvent::constructFrom([
        'id' => 'evt_trial_will_end_'.bin2hex(random_bytes(4)),
        'object' => 'event',
        'type' => 'customer.subscription.trial_will_end',
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

it('dispatches TrialWillEnd event when team is found', function () {
    $team = ChatTeam::factory()->create([
        'tolerycad_stripe_id' => 'cus_trial_team',
    ]);

    $trialEnd = Carbon::now()->addDays(3)->timestamp;

    $response = dispatchTrialWillEndWebhook([
        'id' => 'sub_test',
        'customer' => 'cus_trial_team',
        'trial_end' => $trialEnd,
    ]);

    $response->assertOk();

    Event::assertDispatched(TrialWillEnd::class, function ($event) use ($team, $trialEnd) {
        return $event->team->is($team)
            && $event->trialEndsAt->timestamp === $trialEnd;
    });
});

it('does not dispatch when no team matches the customer', function () {
    $response = dispatchTrialWillEndWebhook([
        'id' => 'sub_unknown',
        'customer' => 'cus_does_not_exist',
        'trial_end' => Carbon::now()->addDays(3)->timestamp,
    ]);

    $response->assertOk();

    Event::assertNotDispatched(TrialWillEnd::class);
});

it('does not dispatch when trial_end is missing from payload', function () {
    ChatTeam::factory()->create(['tolerycad_stripe_id' => 'cus_no_trial_end']);

    $response = dispatchTrialWillEndWebhook([
        'id' => 'sub_missing',
        'customer' => 'cus_no_trial_end',
        // trial_end omitted
    ]);

    $response->assertOk();

    Event::assertNotDispatched(TrialWillEnd::class);
});
