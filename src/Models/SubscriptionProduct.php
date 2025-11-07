<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tolery\AiCad\Database\Factories\SubscriptionProductFactory;
use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Observers\SubscriptionProductObserver;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $price
 * @property bool $active
 * @property int $files_allowed
 * @property string $stripe_id
 * @property string $stripe_price_id
 * @property ResetFrequency $frequency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubscriptionPrice> $prices
 * @property-read ?SubscriptionPrice $activeMonthlyPrice
 * @property-read ?SubscriptionPrice $activeYearlyPrice
 */
#[ObservedBy([SubscriptionProductObserver::class])]
class SubscriptionProduct extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'frequency' => ResetFrequency::class,
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPrice::class, 'subscription_product_id');
    }

    public function activePrices(): HasMany
    {
        return $this->prices()->where('active', true)->whereNull('archived_at');
    }

    public function getActiveMonthlyPriceAttribute(): ?SubscriptionPrice
    {
        /** @var SubscriptionPrice|null */
        return $this->prices()
            ->where('active', true)
            ->whereNull('archived_at')
            ->where('interval', 'month')
            ->first();
    }

    public function getActiveYearlyPriceAttribute(): ?SubscriptionPrice
    {
        /** @var SubscriptionPrice|null */
        return $this->prices()
            ->where('active', true)
            ->whereNull('archived_at')
            ->where('interval', 'year')
            ->first();
    }

    public function price(): Attribute
    {
        return new Attribute(
            get: function (?int $value) {
                return $value ? $value / 100 : null;
            },
            set: function (?int $value) {
                return $value ? $value * 100 : null;
            }
        );
    }

    /**
     * @return array{name: string, active: bool, description: string, metadata: array<string, string>, tax_code: string, shippable: bool}
     */
    public function toStripeObject(): array
    {
        return [
            'name' => $this->name,
            'active' => $this->active,
            'description' => $this->description,
            'metadata' => [
                'files_allowed' => (string) $this->files_allowed,
                'frequency' => $this->frequency->value,
                'laravel_product_id' => (string) $this->id,
            ],
            'tax_code' => 'txcd_10103101',
            'shippable' => false,
        ];
    }

    public function toStripePriceObject(): array
    {
        return [
            'nickname' => $this->name.' price',
            'tax_behavior' => 'exclusive',
            'currency' => config('cashier.currency'),
            'unit_amount' => $this->getRawOriginal('price'),
            'product' => $this->stripe_id,
            'transfer_lookup_key' => true,
            'lookup_key' => $this->getStripePriceKey(),
            'recurring' => [
                'interval' => $this->frequency->stripInterval(),
                'interval_count' => 1,
            ],
        ];
    }

    public function getStripePriceKey(): string
    {
        return "price_for_{$this->id}_subscription";
    }

    protected static function newFactory(): SubscriptionProductFactory
    {
        return SubscriptionProductFactory::new();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('price', 'asc');
    }
}
