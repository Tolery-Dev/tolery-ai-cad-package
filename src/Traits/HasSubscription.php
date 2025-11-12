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
        
        $firstItem = $subscription->items->first();
        
        if (! $firstItem) {
            return null;
        }
        
        $productId = $firstItem->stripe_product;

        return SubscriptionProduct::query()
            ->where('stripe_id', $productId)
            ->first();
    }
}
