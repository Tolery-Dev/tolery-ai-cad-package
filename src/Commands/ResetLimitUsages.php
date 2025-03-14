<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tolery\AiCad\Contracts\Limit;
use Tolery\AiCad\LimitManager;

class ResetLimitUsages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'limit:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset limit usages based on each limit reset frequency.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $limits = app(Limit::class)::whereNotNull('reset_frequency')->get(['id', 'reset_frequency']);

        $affectedRows = 0;

        foreach ($limits as $limit) {
            $affectedRows += DB::table(config('limit.tables.model_has_limits'))
                ->where('used_amount', '>', 0)
                ->where('next_reset', '<=', now())
                ->update([
                    'used_amount' => 0,
                    'last_reset' => now(),
                    'next_reset' => app(LimitManager::class)->getNextReset($limit->reset_frequency, now()),
                ]);
        }

        $this->info("$affectedRows usages/rows where resetted.");
    }
}
