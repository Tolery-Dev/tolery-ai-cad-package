<?php

namespace Tolery\AiCad\Observers;

use Tolery\AiCad\Jobs\Stripe\ProductCreate;
use Tolery\AiCad\Jobs\Stripe\ProductDelete;
use Tolery\AiCad\Jobs\Stripe\ProductUpdate;
use Tolery\AiCad\Models\SubscriptionProduct;

class SubscriptionProductObserver
{
    /**
     * Handle the SubscriptionProduct "created" event.
     */
    public function created(SubscriptionProduct $subscriptionProduct): void
    {
        // On crée le produit côté Stripe également
        ProductCreate::dispatch($subscriptionProduct);
    }

    /**
     * Handle the SubscriptionProduct "updated" event.
     */
    public function updated(SubscriptionProduct $subscriptionProduct): void
    {
        //
        if ($subscriptionProduct->stripe_id) {
            ProductUpdate::dispatch($subscriptionProduct, $subscriptionProduct->wasChanged('price'));
        } else {
            ProductCreate::dispatch($subscriptionProduct);
        }
    }

    /**
     * Handle the SubscriptionProduct "deleted" event.
     */
    public function deleted(SubscriptionProduct $subscriptionProduct): void
    {
        ProductDelete::dispatch($subscriptionProduct->stripe_id);
    }
}
