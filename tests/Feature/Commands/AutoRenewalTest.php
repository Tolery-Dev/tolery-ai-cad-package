<?php

use Tolery\AiCad\Commands\LimitsAutoRenewal;
use Tolery\AiCad\Jobs\LimitRenew;
use Tolery\AiCad\Models\Limit;

use function Pest\Laravel\artisan;

test('0 subscription to be renewed', function () {

    Queue::fake();

    Limit::factory()
        ->current()
        ->create();

    artisan(LimitsAutoRenewal::class)
        ->expectsOutput('0 subscriptions will be renewed')
        ->assertExitCode(0);
});

test('subscriptions to be renewed', function () {

    Queue::fake();

    Limit::factory()
        ->count(10)
        ->past()
        ->create();

    Limit::factory()
        ->count(10)
        ->current()
        ->create();

    artisan(LimitsAutoRenewal::class)
        ->expectsOutput('10 subscriptions will be renewed')
        ->assertExitCode(0);

    Queue::assertPushed(LimitRenew::class, 10);
});
