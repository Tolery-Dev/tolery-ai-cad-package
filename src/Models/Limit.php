<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $subscription_product_id
 * @property int $used_amount
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ChatTeam|null $team
 * @property SubscriptionProduct|null $product
 */
class Limit extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'subscription_product_id',
        'used_amount',
        'start_date',
        'end_date',
    ];

    protected $table = 'subscription_has_limits';

    /**
     * @return array{
     *      'start_date': 'datetime',
     *      'end_date': 'datetime',
     * }
     */
    public function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

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
        return $this->belongsTo(ChatTeam::class);
    }
}
