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
 * @property string $session_id
 * @property string $name
 * @property MaterialFamily $material_family
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Chat extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array{
     *     'material_family': '\Tolery\AiCad\Enum\MaterialFamily'
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
        return $this->belongsTo(config('ai-cad.chat_team_model'));
    }

    /**
     * @return BelongsTo<ChatUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('ai-cad.chat_user_model'));
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
