<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $used_amount
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Limit extends Model
{
    protected $table = 'subscription_has_limits';

    /**
     * @return BelongsTo<SubscriptionProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProduct::class, 'subscription_product_id');
    }

    /**
     * @return BelongsTo<ChatTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(config('ai-cad.chat_team_model')); // @phpstan-ignore-line
    }
}
