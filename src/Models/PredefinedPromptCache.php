<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $prompt_hash
 * @property string $prompt_text
 * @property string|null $obj_cache_path
 * @property string|null $step_cache_path
 * @property string|null $json_cache_path
 * @property string|null $technical_drawing_cache_path
 * @property string|null $screenshot_cache_path
 * @property string $chat_response
 * @property array|null $simulated_steps
 * @property int $hits_count
 * @property Carbon $generated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PredefinedPromptCache extends Model
{
    protected $table = 'predefined_prompt_cache';

    protected $fillable = [
        'prompt_hash',
        'prompt_text',
        'obj_cache_path',
        'step_cache_path',
        'json_cache_path',
        'technical_drawing_cache_path',
        'screenshot_cache_path',
        'chat_response',
        'simulated_steps',
        'hits_count',
        'generated_at',
    ];

    /**
     * @return array{
     *     'simulated_steps': 'array',
     *     'generated_at': 'datetime',
     * }
     */
    public function casts(): array
    {
        return [
            'simulated_steps' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Get cache entries older than specified days
     */
    public function scopeOlderThan($query, int $days)
    {
        return $query->where('generated_at', '<', now()->subDays($days));
    }

    /**
     * Get most popular cached prompts
     */
    public function scopeMostPopular($query, int $limit = 10)
    {
        return $query->orderByDesc('hits_count')->limit($limit);
    }

    /**
     * Increment hits counter
     */
    public function incrementHits(): void
    {
        $this->increment('hits_count');
    }

    /**
     * Check if cache has all required files
     */
    public function hasAllFiles(): bool
    {
        return ! empty($this->obj_cache_path)
            && ! empty($this->step_cache_path)
            && ! empty($this->json_cache_path);
    }

    /**
     * Get all file paths as array
     */
    public function getFilePaths(): array
    {
        return array_filter([
            'obj' => $this->obj_cache_path,
            'step' => $this->step_cache_path,
            'json' => $this->json_cache_path,
            'technical_drawing' => $this->technical_drawing_cache_path,
            'screenshot' => $this->screenshot_cache_path,
        ]);
    }
}
