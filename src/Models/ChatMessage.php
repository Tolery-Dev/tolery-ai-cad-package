<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\AiCad;

class ChatMessage extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AiCad::$userModel);
    }

    public function getObjUrl(): ?string
    {
        return $this->ai_cad_path ?
            Storage::providesTemporaryUrls() ? Storage::temporaryUrl($this->ai_cad_path, now()->addMinutes(5)) : Storage::url($this->ai_cad_path)
            : null;
    }

    public function getObjName(): ?string
    {
        return $this->ai_cad_path ? File::name($this->ai_cad_path) : null;
    }
}
