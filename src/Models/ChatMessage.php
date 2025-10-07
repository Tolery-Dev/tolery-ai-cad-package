<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $role
 * @property string $message
 * @property string|null $ai_cad_path
 * @property string|null $ai_json_edge_path
 * @property string|null $ai_step_path
 * @property string|null $ai_technical_drawing_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChatMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $guarded = [];

    protected $attributes = [
        'role' => self::ROLE_USER,
    ];

    public function casts(): array
    {
        return [
            'edge_object_map_id' => AsCollection::class,
        ];
    }

    /**
     * @return BelongsTo<ChatUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class);
    }

    public function getObjUrl(): ?string
    {
        if (! $this->ai_cad_path) {
            return null;
        }

        // Si c'est déjà une URL complète (http/https), on la retourne telle quelle
        if (filter_var($this->ai_cad_path, FILTER_VALIDATE_URL)) {
            return $this->ai_cad_path;
        }

        // Sinon, c'est un chemin Storage local
        return Storage::providesTemporaryUrls()
            ? Storage::temporaryUrl($this->ai_cad_path, now()->addMinutes(5))
            : Storage::url($this->ai_cad_path);
    }

    public function objUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ai_cad_path
        );
    }

    public function getObjName(): ?string
    {
        return $this->ai_cad_path ? File::name($this->ai_cad_path) : null;
    }

    public function getJSONEdgeUrl(): ?string
    {
        if (! $this->ai_json_edge_path) {
            return null;
        }

        // Si c'est déjà une URL complète (http/https), on la retourne telle quelle
        if (filter_var($this->ai_json_edge_path, FILTER_VALIDATE_URL)) {
            return $this->ai_json_edge_path;
        }

        // Sinon, c'est un chemin Storage local
        return Storage::providesTemporaryUrls()
            ? Storage::temporaryUrl($this->ai_json_edge_path, now()->addMinutes(5))
            : Storage::url($this->ai_json_edge_path);
    }

    public function getTechnicalDrawingUrl(): ?string
    {
        if (! $this->ai_technical_drawing_path) {
            return null;
        }

        // Si c'est déjà une URL complète (http/https), on la retourne telle quelle
        if (filter_var($this->ai_technical_drawing_path, FILTER_VALIDATE_URL)) {
            return $this->ai_technical_drawing_path;
        }

        // Sinon, c'est un chemin Storage local
        return Storage::providesTemporaryUrls()
            ? Storage::temporaryUrl($this->ai_technical_drawing_path, now()->addMinutes(5))
            : Storage::url($this->ai_technical_drawing_path);
    }

    public function getStepUrl(): ?string
    {
        if (! $this->ai_step_path) {
            return null;
        }

        // Si c'est déjà une URL complète (http/https), on la retourne telle quelle
        if (filter_var($this->ai_step_path, FILTER_VALIDATE_URL)) {
            return $this->ai_step_path;
        }

        // Sinon, c'est un chemin Storage local
        return Storage::providesTemporaryUrls()
            ? Storage::temporaryUrl($this->ai_step_path, now()->addMinutes(5))
            : Storage::url($this->ai_step_path);
    }
}
