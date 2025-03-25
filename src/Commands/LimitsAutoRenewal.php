<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Tolery\AiCad\Jobs\LimitRenew;
use Tolery\AiCad\Models\Limit;

class LimitsAutoRenewal extends Command
{
    public $signature = 'ai-cad:auto-renewal';

    public $description = 'Renew all subscriptions';

    public function handle(): int
    {
        $limits = Limit::query()
            ->wherePast('end_date')
            ->get();

        $this->comment("{$limits->count()} subscriptions will be renewed");

        $limits->each(fn (Limit $limit) => LimitRenew::dispatch($limit));

        return self::SUCCESS;
    }
}
