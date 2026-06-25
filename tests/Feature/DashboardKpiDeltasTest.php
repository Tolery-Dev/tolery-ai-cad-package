<?php

use Flux\DateRange;
use Illuminate\Support\Facades\Date;
use Laravel\Cashier\Subscription;
use Tolery\AiCad\Livewire\Admin\Dashboard;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    Date::setTestNow('2026-05-22 12:00:00');
});

afterEach(function () {
    Date::setTestNow();
});

it('computes the activity delta against the previous comparable window', function () {
    $team = ChatTeam::factory()->create();
    // Current 7-day window: 3 conversations.
    Chat::factory()->count(3)->create(['team_id' => $team->id, 'created_at' => now()->subDays(2)]);
    // Previous 7-day window: 2 conversations.
    Chat::factory()->count(2)->create(['team_id' => $team->id, 'created_at' => now()->subDays(9)]);

    $dashboard = new Dashboard;
    $dashboard->range = new DateRange(now()->subDays(7), now());

    $kpis = $dashboard->getKpis();

    expect($kpis['conversation_count'])->toBe(3)
        ->and($kpis['deltas']['conversation_count'])->toBe(1)
        // No revenue in either window → no comparable base → null delta.
        ->and($kpis['deltas']['total_revenue'])->toBeNull();
});

it('splits active subscribers between paying and trialing counts', function () {
    $payingTeam = ChatTeam::factory()->create();
    $trialTeam = ChatTeam::factory()->create();

    Subscription::query()->forceCreate([
        'team_id' => $payingTeam->id,
        'type' => 'default',
        'stripe_id' => 'sub_paying',
        'stripe_status' => 'active',
        'stripe_price' => 'price_x',
        'quantity' => 1,
    ]);

    Subscription::query()->forceCreate([
        'team_id' => $trialTeam->id,
        'type' => 'default',
        'stripe_id' => 'sub_trial',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_x',
        'quantity' => 1,
        'trial_ends_at' => now()->addDays(5),
    ]);

    $kpis = (new Dashboard)->getKpis();

    expect($kpis['paying_count'])->toBe(1)
        ->and($kpis['trialing_count'])->toBe(1)
        ->and($kpis['subscription_count'])->toBe(2);
});

it('reports the à-la-pièce revenue (HT) and the number of unit purchases', function () {
    $team = ChatTeam::factory()->create();
    $chat = Chat::factory()->create(['team_id' => $team->id]);

    foreach (['pi_a', 'pi_b'] as $paymentIntent) {
        FilePurchase::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'stripe_payment_intent_id' => $paymentIntent,
            'amount' => 2900, // 29,00 € HT
            'currency' => 'eur',
            'purchased_at' => now()->subDay(),
        ]);
    }

    $kpis = (new Dashboard)->getKpis();

    expect($kpis['purchase_count'])->toBe(2)
        ->and($kpis['purchase_revenue'])->toEqual(58.0); // 2 × 29,00 € HT
});
