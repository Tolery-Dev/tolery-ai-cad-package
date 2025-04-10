<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tolery\AiCad\Database\Factories\SubscriptionProductFactory;
use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Observers\SubscriptionProductObserver;

/**
 * @property string $name
 * @property string $description
 * @property int $price
 * @property bool $active
 * @property int $files_allowed
 * @property string $stripe_id
 * @property string $stripe_price_id
 * @property ResetFrequency $frequency
 */
#[ObservedBy([SubscriptionProductObserver::class])]
class SubscriptionProduct extends Model
{
    use HasFactory;

    /**
     * @return array{
     *     'active':'boolean',
     *      'frequency': 'Tolery\AiCad\Enum\ResetFrequency',
     * }
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'frequency' => ResetFrequency::class,
        ];
    }

    public function price(): Attribute
    {
        return new Attribute(
            get: function (int $value) {
                return $value / 100;
            },
            set: function (int $value) {
                return $value * 100;
            }
        );
    }

    public function toStripeObject(): array
    {
        return [
            'name' => $this->name,
            'active' => $this->active,
            'description' => $this->description,
            'metadata' => ['files_allowed' => $this->files_allowed, 'frequency' => $this->frequency->value],
            'tax_code' => 'txcd_10103101', // Logiciel en tant que service (SaaS), téléchargement électronique, usage professionnel
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
