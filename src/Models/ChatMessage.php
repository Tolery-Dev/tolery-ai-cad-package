<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tolery\AiCad\AiCad;

class ChatMessage extends Model
{

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AiCad::$userModel);
    }
}
