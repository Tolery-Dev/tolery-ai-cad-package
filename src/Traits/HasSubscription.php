<?php

namespace Tolery\AiCad\Traits;

use Tolery\AiCad\Models\SubscriptionProduct;

trait HasSubscription
{
    public function getSubscriptionProduct($team): SubscriptionProduct
    {
        return SubscriptionProduct::whereStripePriceId($team->subscription()->items->first()->stripe_price)->first();
    }
}
