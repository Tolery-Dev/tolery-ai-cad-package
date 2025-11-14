<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $chat_id
 * @property \Carbon\Carbon $downloaded_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChatDownload extends Model
{
    protected $fillable = [
        'team_id',
        'chat_id',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ChatTeam::class, 'team_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public static function isDownloaded(ChatTeam $team, Chat $chat): bool
    {
        return static::where('team_id', $team->id)
            ->where('chat_id', $chat->id)
            ->exists();
    }
}
