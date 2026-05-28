<?php

namespace Tolery\AiCad\Jobs\Stripe;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\AiCadStripe;

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
     * Execute the job. Uses the AI-CAD Stripe account (AICAD_STRIPE_SECRET),
     * NOT the host app's Cashier account — the two are distinct.
     *
     * @throws ApiErrorException
     */
    public function handle(AiCadStripe $stripe): void
    {
        if (! $this->subscriptionProduct->stripe_id) {
            return;
        }

        try {
            $this->pushToStripe($stripe->client());
        } catch (InvalidRequestException $e) {
            if (! $this->isStripeMissingResource($e)) {
                throw $e;
            }

            // Stale stripe_id: the product was deleted on Stripe but the local
            // row still references it. Reset the local mapping and recreate the
            // product on Stripe instead of failing the job.
            Log::warning('[AiCad] Stripe product missing, recreating', [
                'subscription_product_id' => $this->subscriptionProduct->id,
                'old_stripe_id' => $this->subscriptionProduct->stripe_id,
                'stripe_code' => $e->getStripeCode(),
            ]);

            $this->recreateMissingStripeProduct();
        }
    }

    protected function pushToStripe(StripeClient $stripeClient): void
    {
        $price = null;

        if ($this->updatePrice) {
            // On désactive tous les prix précédents
            $prices = $stripeClient->prices->all([
                'product' => $this->subscriptionProduct->stripe_id,
                'active' => true,
            ]);

            foreach ($prices->data as $price) {
                $stripeClient->prices->update($price->id, ['active' => false]);
            }

            $price = $stripeClient->prices->create($this->subscriptionProduct->toStripePriceObject());
        }

        $productParams = $this->subscriptionProduct->toStripeObject();

        if ($price) {
            $productParams['default_price'] = $price->id;
        }

        $stripeClient->products->update($this->subscriptionProduct->stripe_id, $productParams);
    }

    protected function recreateMissingStripeProduct(): void
    {
        $this->subscriptionProduct->forceFill([
            'stripe_id' => null,
            'stripe_price_id' => null,
        ])->saveQuietly();

        ProductCreate::dispatch($this->subscriptionProduct->fresh());
    }

    /**
     * True when Stripe rejected the call because the referenced resource
     * (product / price) does not exist anymore.
     */
    public static function isStripeMissingResource(\Throwable $e): bool
    {
        return $e instanceof InvalidRequestException
            && $e->getStripeCode() === 'resource_missing';
    }
}
