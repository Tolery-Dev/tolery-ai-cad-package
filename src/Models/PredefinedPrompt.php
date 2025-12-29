<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tolery\AiCad\Enum\MaterialFamily;

/**
 * @property int $id
 * @property string $name
 * @property string $prompt_text
 * @property ?MaterialFamily $material_family
 * @property bool $active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PredefinedPrompt extends Model
{
    protected $fillable = [
        'name',
        'prompt_text',
        'material_family',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'material_family' => MaterialFamily::class,
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order');
    }

    public function scopeForMaterial(Builder $query, ?MaterialFamily $material): void
    {
        $query->when($material, fn ($q) => $q->where('material_family', $material));
    }
}
