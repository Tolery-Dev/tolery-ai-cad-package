<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Tolery\AiCad\Models\PredefinedPrompt;

class MigratePrompts extends Command
{
    protected $signature = 'ai-cad:migrate-prompts {--force : Force migration even if prompts already exist}';

    protected $description = 'Migrate predefined prompts from config file to database';

    public function handle(): int
    {
        $prompts = config('ai-cad.predefined_prompts', []);

        if (empty($prompts)) {
            $this->warn('No prompts found in config file.');

            return self::SUCCESS;
        }

        $existingCount = PredefinedPrompt::count();

        if ($existingCount > 0 && ! $this->option('force')) {
            $this->warn("Database already contains {$existingCount} prompts.");
            $this->warn('Use --force to add prompts anyway.');

            return self::FAILURE;
        }

        $this->info('Migrating prompts from config to database...');

        $sortOrder = $existingCount;
        $created = 0;

        foreach ($prompts as $name => $promptText) {
            PredefinedPrompt::create([
                'name' => $name,
                'prompt_text' => $promptText,
                'material_family' => null,
                'active' => true,
                'sort_order' => $sortOrder++,
            ]);
            $created++;
            $this->line("  ✓ Created: {$name}");
        }

        $this->newLine();
        $this->info("Successfully migrated {$created} prompts to database.");
        $this->newLine();
        $this->comment('You can now set AICAD_PROMPTS_SOURCE=database in your .env file');
        $this->comment('and manage prompts via the admin panel at /admin/tolerycad/prompts');

        return self::SUCCESS;
    }
}
