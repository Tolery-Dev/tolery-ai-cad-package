<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;

class AiCadCommand extends Command
{
    public $signature = 'ai-cad';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
