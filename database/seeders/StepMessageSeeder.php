<?php

namespace Tolery\AiCad\Database\Seeders;

use Illuminate\Database\Seeder;
use Tolery\AiCad\Models\StepMessage;

class StepMessageSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Seeding default step messages...');

        $defaultMessages = StepMessage::getDefaultStepMessages();

        $sortOrder = 1;
        foreach ($defaultMessages as $stepKey => $messages) {
            StepMessage::updateOrCreate(
                ['step_key' => $stepKey],
                [
                    'label' => $this->getLabelForStepKey($stepKey),
                    'messages' => $messages,
                    'sort_order' => $sortOrder++,
                    'active' => true,
                ]
            );
        }

        $this->command->info('✅ Successfully seeded '.count($defaultMessages).' step messages');
    }

    private function getLabelForStepKey(string $stepKey): string
    {
        return match ($stepKey) {
            'analysis' => 'Analyse',
            'parameters' => 'Paramètres',
            'generation_code' => 'Génération du code',
            'export' => 'Export',
            'complete' => 'Finalisation',
            default => ucfirst(str_replace('_', ' ', $stepKey)),
        };
    }
}
