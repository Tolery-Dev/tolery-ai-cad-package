<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tolery\AiCad\Database\Factories\SubscriptionPriceFactory;

/**
 * @property int $id
 * @property int $subscription_product_id
 * @property string $stripe_price_id
 * @property int $amount
 * @property string $currency
 * @property string $interval
 * @property bool $active
 * @property ?\Carbon\Carbon $archived_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read SubscriptionProduct $product
 * @property-read float $price
 */
class SubscriptionPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_product_id',
        'stripe_price_id',
        'amount',
        'currency',
        'interval',
        'active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'archived_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProduct::class, 'subscription_product_id');
    }

    public function price(): Attribute
    {
        return new Attribute(
            get: fn (mixed $value, array $attributes) => (float) ($attributes['amount'] / 100)
        );
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)
            ->whereNull('archived_at');
    }

    public function scopeForProduct(Builder $query, int $productId): void
    {
        $query->where('subscription_product_id', $productId);
    }

    public function scopeMonthly(Builder $query): void
    {
        $query->where('interval', 'month');
    }

    public function scopeYearly(Builder $query): void
    {
        $query->where('interval', 'year');
    }

    public function archive(): void
    {
        $this->update([
            'active' => false,
            'archived_at' => now(),
        ]);
    }

    public function isArchived(): bool
    {
        return ! $this->active || $this->archived_at !== null;
    }

    protected static function newFactory(): SubscriptionPriceFactory
    {
        return SubscriptionPriceFactory::new();
    }
}
