<?php

namespace Tolery\AiCad\Traits;

use Tolery\AiCad\Models\SubscriptionProduct;

trait HasSubscription
{
    public function getSubscriptionProduct(): ?SubscriptionProduct
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return null;
        }

        $subscription->loadMissing('items');

        /** @var \Laravel\Cashier\SubscriptionItem|null $firstItem */
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
