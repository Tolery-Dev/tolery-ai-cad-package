<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $step_key
 * @property string $label
 * @property array $messages
 * @property int $sort_order
 * @property bool $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class StepMessage extends Model
{
    protected $fillable = [
        'step_key',
        'label',
        'messages',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }

    /**
     * Returns the step messages in the format expected by chatbot.blade.php.
     * Format: ['step_key' => ['message1', 'message2', ...], ...]
     *
     * @return array<string, array<int, string>>
     */
    public static function getStepMessagesForFrontend(): array
    {
        $stepMessages = static::query()
            ->active()
            ->ordered()
            ->get();

        $result = [];
        foreach ($stepMessages as $stepMessage) {
            $result[$stepMessage->step_key] = $stepMessage->messages;
        }

        // Fallback to default messages if no data in DB
        if (empty($result)) {
            return static::getDefaultStepMessages();
        }

        return $result;
    }

    /**
     * Returns the default step messages (hardcoded fallback).
     *
     * @return array<string, array<int, string>>
     */
    public static function getDefaultStepMessages(): array
    {
        return [
            'analysis' => [
                'Analyse des dimensions de la pièce...',
                'Vérification des contraintes de fabrication...',
                'Validation de la géométrie...',
            ],
            'parameters' => [
                'Calcul des paramètres de génération...',
                'Optimisation de la géométrie...',
                'Définition des tolérances...',
            ],
            'generation_code' => [
                'Génération du code CAO...',
                'Construction de la géométrie 3D...',
                'Application des opérations...',
            ],
            'export' => [
                'Export des fichiers STEP et PDF technique...',
                'Génération de la mise en plan...',
                'Création du rendu 3D...',
            ],
            'complete' => [
                'Finalisation des exports...',
                'Vérification de la qualité...',
                'Pièce prête !',
            ],
        ];
    }
}
