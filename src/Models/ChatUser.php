<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

abstract class ChatUser extends User
{
    /**
     * @return BelongsTo<ChatTeam, $this>
     */
    abstract public function team(): BelongsTo;
}
