<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Tolery\AiCad\Models\Limit;

class LimitRenew implements ShouldQueue
{
    use Queueable;

    public function __construct(public Limit $limit) {}

    public function handle(): void
    {
        $team = $this->limit->team;

        if (! $team->subscribed()) {
            return;
        }

        $product = $this->limit->product;
        $frequency = $product?->frequency;

        if ($frequency === null) {
            Log::warning('[LimitRenew] Skipping renewal: product or frequency is null', [
                'limit_id' => $this->limit->id,
                'team_id' => $this->limit->team_id,
                'product_id' => $product?->id,
            ]);

            return;
        }

        $newLimit = $this->limit->replicate();
        $newLimit->start_date = now();
        $newLimit->end_date = $frequency->addTime(now());
        $newLimit->save();
    }
}
