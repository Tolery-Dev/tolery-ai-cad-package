<?php

namespace Tolery\AiCad\Services;

use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;
use Tolery\AiCad\Models\SubscriptionPrice;
use Tolery\AiCad\Models\SubscriptionProduct;

class StripeSyncService
{
    protected StripeClient $stripe;

    public function __construct(
        protected AiCadStripe $aiCadStripe
    ) {
        $this->stripe = $this->aiCadStripe->client();
    }

    /**
     * @throws ApiErrorException
     */
    public function syncProductsFromStripe(): array
    {
        $synced = [
            'products' => 0,
            'prices' => 0,
            'errors' => [],
        ];

        try {
            $stripeProducts = $this->stripe->products->all([
                'active' => true,
                'limit' => 100,
            ]);

            foreach ($stripeProducts->data as $stripeProduct) {
                try {
                    $this->syncProduct($stripeProduct);
                    $synced['products']++;

                    $stripePrices = $this->stripe->prices->all([
                        'product' => $stripeProduct->id,
                        'active' => true,
                        'limit' => 100,
                    ]);

                    foreach ($stripePrices->data as $stripePrice) {
                        $this->syncPrice($stripePrice, $stripeProduct->id);
                        $synced['prices']++;
                    }
                } catch (\Exception $e) {
                    $synced['errors'][] = "Product {$stripeProduct->id}: {$e->getMessage()}";
                }
            }
        } catch (ApiErrorException $e) {
            $synced['errors'][] = "Stripe API Error: {$e->getMessage()}";
            throw $e;
        }

        return $synced;
    }

    protected function syncProduct(Product $stripeProduct): SubscriptionProduct
    {
        $filesAllowed = $stripeProduct->metadata->files_allowed ?? null;

        return SubscriptionProduct::updateOrCreate(
            ['stripe_id' => $stripeProduct->id],
            [
                'name' => $stripeProduct->name,
                'description' => $stripeProduct->description ?? '',
                'active' => $stripeProduct->active,
                'files_allowed' => $filesAllowed ? (int) $filesAllowed : null,
            ]
        );
    }

    protected function syncPrice(Price $stripePrice, string $stripeProductId): ?SubscriptionPrice
    {
        if ($stripePrice->type !== 'recurring') {
            return null;
        }

        $product = SubscriptionProduct::where('stripe_id', $stripeProductId)->first();

        if (! $product) {
            return null;
        }

        return SubscriptionPrice::updateOrCreate(
            ['stripe_price_id' => $stripePrice->id],
            [
                'subscription_product_id' => $product->id,
                'amount' => $stripePrice->unit_amount,
                'currency' => $stripePrice->currency,
                'interval' => $stripePrice->recurring->interval,
                'active' => $stripePrice->active,
            ]
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function archiveOldPrices(): int
    {
        $archived = 0;

        $activePrices = SubscriptionPrice::active()->get();

        foreach ($activePrices as $price) {
            try {
                $stripePrice = $this->stripe->prices->retrieve($price->stripe_price_id);

                if (! $stripePrice->active) {
                    $price->archive();
                    $archived++;
                }
            } catch (ApiErrorException $e) {
                continue;
            }
        }

        return $archived;
    }

    public function createStripeProductFromLocal(SubscriptionProduct $product): Product
    {
        return $this->stripe->products->create([
            'name' => $product->name,
            'description' => $product->description,
            'active' => $product->active,
            'metadata' => [
                'files_allowed' => (string) $product->files_allowed,
                'laravel_product_id' => (string) $product->id,
            ],
        ]);
    }

    public function createStripePriceFromLocal(SubscriptionPrice $price): Price
    {
        $product = $price->product;

        if (! $product->stripe_id) {
            throw new \RuntimeException("Product {$product->id} must have a stripe_id before creating prices");
        }

        return $this->stripe->prices->create([
            'product' => $product->stripe_id,
            'unit_amount' => $price->amount,
            'currency' => $price->currency,
            'recurring' => [
                'interval' => $price->interval,
            ],
            'active' => $price->active,
        ]);
    }
}
