<?php

namespace Tolery\AiCad\Jobs\Stripe;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Tolery\AiCad\Models\SubscriptionProduct;

class ProductCreate implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected SubscriptionProduct $subscriptionProduct)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @throws ApiErrorException
     */
    public function handle(): void
    {
        $product = Cashier::stripe()->products->create($this->subscriptionProduct->toStripeObject());

        $price = Cashier::stripe()->prices->create([
            ...$this->subscriptionProduct->toStripePriceObject(),
            'product' => $product->id,
        ]);

        Cashier::stripe()->products->update($product->id, ['default_price' => $price->id]);

        $this->subscriptionProduct->updateQuietly([
            'stripe_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);
    }
}
