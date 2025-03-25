<?php

use Tolery\AiCad\Jobs\LimitRenew;
use Tolery\AiCad\Models\Limit;
use function Pest\Laravel\assertDatabaseCount;

test('limit renew', function(){

    $limit = Limit::factory()
        ->past()
        ->create();

    $limitRenewJob = new LimitRenew($limit);

    $limitRenewJob->handle();

    assertDatabaseCount( 'subscription_has_limits', 2);

    $newLimite = Limit::query()->whereTodayOrAfter('end_date')->first();

    assert($newLimite->start_date->isToday());
});
