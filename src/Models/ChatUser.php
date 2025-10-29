<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

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
