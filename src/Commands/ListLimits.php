<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Tolery\AiCad\Contracts\Limit;

class ListLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'limit:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show limits';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $columns = ['name', 'plan', 'allowed_amount', 'reset_frequency'];

        $limits = app(Limit::class)::all($columns);

        if ($limits->isEmpty()) {
            $this->info('No limits available.');

            return;
        }

        $this->table($columns, $limits);
    }
}
