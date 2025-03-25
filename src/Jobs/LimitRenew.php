<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Tolery\AiCad\Models\Limit;

class LimitRenew implements ShouldQueue
{
    use Queueable;

    public function __construct(public Limit $limit) {}

    public function handle(): void
    {
        // créer une nouvelle limite à partir de l'ancienne
        $newLimit = $this->limit->replicate();

        $newLimit->start_date = now();
        $newLimit->end_date = $this->limit->product->frequency->addTime(now());
        $newLimit->save();
    }
}
