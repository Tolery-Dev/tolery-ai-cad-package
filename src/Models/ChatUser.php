<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

/**
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property int|null $team_id
 * @property-read ChatTeam|null $team
 */
class ChatUser extends User
{
    protected $table = 'users';

    protected $guarded = [];

    /**
     * @return BelongsTo<ChatTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(ChatTeam::class);
    }
}
