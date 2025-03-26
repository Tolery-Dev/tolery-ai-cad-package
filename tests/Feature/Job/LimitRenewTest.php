<?php

use Laravel\Cashier\Subscription;
use Tolery\AiCad\Jobs\LimitRenew;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\Limit;

use function Pest\Laravel\assertDatabaseCount;

test('limit renew', function () {

    $limit = Limit::factory()
        ->past()
        ->create();

    $team = Mockery::mock(ChatTeam::class);
    $team->shouldReceive('subscribed')->andReturn(true);

    $limit->setRelation('team', $team);

    $limitRenewJob = new LimitRenew($limit);

    $limitRenewJob->handle();

    assertDatabaseCount('subscription_has_limits', 2);

    $newLimite = Limit::query()->whereTodayOrAfter('end_date')->first();

    assert($newLimite->start_date->isToday());
});


test('dont renew limit for unsubscribe team', function () {

    $team = ChatTeam::factory()->create();

    $limit = Limit::factory()
        ->for($team, 'team')
        ->past()
        ->create();

    Subscription::factory()
        ->canceled()
        ->create([
            'team_id' => $team->id,
        ]);


    $limitRenewJob = new LimitRenew($limit);

    $limitRenewJob->handle();

    assertDatabaseCount('subscription_has_limits', 1);
});
