<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Tolery\AiCad\Traits\HasLimits;
use Tolery\AiCad\Traits\HasSubscription;

/**
 * @property int $id
 * @property string $name
 * @property int|null $user_id
 */
class ChatTeam extends Model
{
    use Billable;
    use HasFactory;
    use HasLimits;
    use HasSubscription;

    protected $table = 'teams';

    protected $guarded = [];

    public function getForeignKey(): string
    {
        return 'team_id';
    }

    /**
     * @return HasMany<Chat, $this>
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * @return HasMany<FilePurchase, $this>
     */
    public function FilesPurchase(): HasMany
    {
        return $this->hasMany(FilePurchase::class);
    }

    /**
     * Vérifie si la team est un beta testeur avec accès libre aux fichiers.
     * Peut être overridée par le modèle de l'application (config ai-cad.chat_team_model).
     */
    public function isBetaTester(): bool
    {
        return false;
    }
}
