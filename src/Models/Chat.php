<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tolery\AiCad\Enum\MaterialFamily;

/**
 * @property string|null $session_id
 * @property int $user_id
 * @property int $team_id
 * @property string|null $name
 * @property MaterialFamily|null $material_family
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Chat extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array{
     *     'material_family': 'Tolery\AiCad\Enum\MaterialFamily'
     * }
     */
    protected $casts = [
        'material_family' => MaterialFamily::class,
    ];

    /**
     * @return BelongsTo<ChatTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(config('ai-cad.chat_team_model')); // @phpstan-ignore-line
    }

    /**
     * @return BelongsTo<ChatUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('ai-cad.chat_user_model')); // @phpstan-ignore-line
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
