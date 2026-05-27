<?php

namespace Tolery\AiCad\Traits;

use Laravel\Cashier\SubscriptionItem;
use Tolery\AiCad\Models\SubscriptionProduct;

trait HasSubscription
{
    /**
     * Generation priority for a paid plan whose product has no explicit
     * `priority` set. Sits above free (0) and below high-tier plans.
     */
    public const DEFAULT_PAID_GENERATION_PRIORITY = 50;

    /**
     * Generation priority (0-100) used to prioritize CAD generation by plan.
     * Higher = higher priority. No active subscription => 0 (free tier).
     */
    public function getGenerationPriority(): int
    {
        $product = $this->getSubscriptionProduct();

        if (! $product) {
            return 0;
        }

        return $product->priority ?? self::DEFAULT_PAID_GENERATION_PRIORITY;
    }

    public function getSubscriptionProduct(): ?SubscriptionProduct
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return null;
        }

        $subscription->loadMissing('items');

        /** @var SubscriptionItem|null $firstItem */
        $firstItem = $subscription->items->first();

        if (! $firstItem) {
            return null;
        }

        /** @phpstan-ignore-next-line - stripe_product is a dynamic attribute from Cashier */
        $productId = $firstItem->stripe_product;

        return SubscriptionProduct::query()
            ->where('stripe_id', $productId)
            ->first();
    }
}
