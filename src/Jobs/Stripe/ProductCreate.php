<?php

namespace Tolery\AiCad\Jobs\Stripe;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stripe\Exception\ApiErrorException;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\AiCadStripe;

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
     * Execute the job. Uses the AI-CAD Stripe account (AICAD_STRIPE_SECRET),
     * NOT the host app's Cashier account — the two are distinct.
     *
     * @throws ApiErrorException
     */
    public function handle(AiCadStripe $stripe): void
    {
        if ($this->subscriptionProduct->stripe_id) {
            return;
        }

        $stripeClient = $stripe->client();

        $product = $stripeClient->products->create($this->subscriptionProduct->toStripeObject());

        $price = $stripeClient->prices->create([
            ...$this->subscriptionProduct->toStripePriceObject(),
            'product' => $product->id,
        ]);

        $stripeClient->products->update($product->id, ['default_price' => $price->id]);

        $this->subscriptionProduct->updateQuietly([
            'stripe_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);
    }
}
