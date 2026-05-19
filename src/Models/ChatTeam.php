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
 * @property string|null $tolerycad_stripe_id
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
     * Mirror local des factures Stripe (table `invoices`).
     *
     * Nommée `localInvoices` et non `invoices` pour ne pas entrer en conflit avec
     * `Billable::invoices(): Collection` de Cashier : une app hôte qui étend ce
     * modèle tout en utilisant le trait Billable provoquerait sinon une erreur
     * fatale d'incompatibilité de signature.
     *
     * @return HasMany<Invoice, $this>
     */
    public function localInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'team_id');
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
