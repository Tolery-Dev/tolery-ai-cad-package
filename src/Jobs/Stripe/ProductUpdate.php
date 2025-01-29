<?php

namespace Tolery\AiCad\Jobs\Stripe;

use Tolery\AiCad\Models\SubscriptionProduct;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class ProductUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected SubscriptionProduct $subscriptionProduct, protected bool $updatePrice = false)
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

        if ($this->subscriptionProduct->stripe_id) {
            $price = null;

            if ($this->updatePrice) {

                // On désactive tous les prix précédents
                $prices = Cashier::stripe()->prices->all([
                    'product' => $this->subscriptionProduct->stripe_id,
                    'active' => true,
                ]);

                foreach ($prices->data as $price) {
                    Cashier::stripe()->prices->update($price->id, ['active' => false]);
                }

                $price = Cashier::stripe()
                    ->prices
                    ->create($this->subscriptionProduct->toStripePriceObject());
            }

            $productParams = $this->subscriptionProduct->toStripeObject();

            if ($price) {
                $productParams['default_price'] = $price->id;
            }

            Cashier::stripe()
                ->products
                ->update($this->subscriptionProduct->stripe_id, $productParams);
        }
    }
}
