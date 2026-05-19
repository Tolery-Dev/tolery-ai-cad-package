<?php

use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Tolery\AiCad\Livewire\Admin\Dashboard;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\SubscriptionProduct;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
});

function createSubscriptionRecord(array $attributes): Subscription
{
    $subscription = new Subscription;
    $subscription->forceFill($attributes)->save();

    return $subscription;
}

it('collects subscriptions currently on a free trial', function () {
    $team = ChatTeam::factory()->create(['name' => 'Acme Corp']);
    SubscriptionProduct::factory()->create([
        'stripe_id' => 'prod_pro',
        'name' => 'Plan Pro',
    ]);

    $subscription = createSubscriptionRecord([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_trial_1',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
        'trial_ends_at' => now()->addDays(10),
    ]);

    (new SubscriptionItem)->forceFill([
        'subscription_id' => $subscription->id,
        'stripe_id' => 'si_trial_1',
        'stripe_product' => 'prod_pro',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ])->save();

    $trials = (new Dashboard)->getTrialingSubscriptions();

    expect($trials)->toHaveCount(1);

    $first = $trials->first();
    expect($first['team_name'])->toBe('Acme Corp')
        ->and($first['product_name'])->toBe('Plan Pro')
        ->and($first['days_left'])->toBeGreaterThan(0);
});

it('excludes active (non-trial) subscriptions from the trial list', function () {
    $team = ChatTeam::factory()->create();

    createSubscriptionRecord([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_active_1',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ]);

    expect((new Dashboard)->getTrialingSubscriptions())->toHaveCount(0);
});

it('counts trialing subscriptions in the dashboard KPIs', function () {
    $trialTeam = ChatTeam::factory()->create();
    $activeTeam = ChatTeam::factory()->create();

    createSubscriptionRecord([
        'team_id' => $trialTeam->id,
        'type' => 'default',
        'stripe_id' => 'sub_trial_kpi',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
        'trial_ends_at' => now()->addDays(5),
    ]);

    createSubscriptionRecord([
        'team_id' => $activeTeam->id,
        'type' => 'default',
        'stripe_id' => 'sub_active_kpi',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ]);

    $kpis = (new Dashboard)->getKpis();

    expect($kpis['trialing_count'])->toBe(1)
        ->and($kpis['subscription_count'])->toBe(2);
});
