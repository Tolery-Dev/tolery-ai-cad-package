<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $chat_id
 * @property string $stripe_payment_intent_id
 * @property int $amount
 * @property string $currency
 * @property \Carbon\Carbon $purchased_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FilePurchase extends Model
{
    protected $fillable = [
        'team_id',
        'chat_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'amount' => 'integer',
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

    public static function hasPurchased(ChatTeam $team, Chat $chat): bool
    {
        return static::where('team_id', $team->id)
            ->where('chat_id', $chat->id)
            ->exists();
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2, ',', ' ').' '.strtoupper($this->currency);
    }
}
