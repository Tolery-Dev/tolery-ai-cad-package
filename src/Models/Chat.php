<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tolery\AiCad\AiCad;

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

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
