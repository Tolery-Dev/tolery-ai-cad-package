<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class ChatUser extends User
{
    /**
     * @return BelongsTo<ChatTeam, $this>
     */
    abstract public function team(): BelongsTo;
}
