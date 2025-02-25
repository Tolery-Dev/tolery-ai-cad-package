<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tolery\AiCad\AiCad;

/**
 * @property string $session_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Chat extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function team(): BelongsTo
    {
        return $this->belongsTo(AiCad::$teamModel);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AiCad::$userModel);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function scopeForTeam(Builder $builder, $team): void
    {
        $builder->where('team_id', $team->id);
    }

    public function getStorageFolder(): string
    {
        return 'ai-chat/'.$this->created_at->format('Y-m').'/chat-'.$this->id;
    }
}
