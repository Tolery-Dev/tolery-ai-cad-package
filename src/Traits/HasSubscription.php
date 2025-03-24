<?php

namespace Tolery\AiCad\Traits;

use Tolery\AiCad\Models\SubscriptionProduct;

trait HasSubscription
{
    public function getSubscriptionProduct(): ?SubscriptionProduct
    {
        $productId = $this->subscription()->items()->first()->stripe_product; // @phpstan-ignore-line

        return SubscriptionProduct::query()
            ->where('stripe_id', $productId)
            ->first();
    }
}
