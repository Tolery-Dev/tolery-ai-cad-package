<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Throwable;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\SubscriptionProduct;

class SubscribeToProduct implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public ChatTeam $team, public SubscriptionProduct $product, public string $paymentMethodId) {}

    /**
     * @throws Throwable
     * @throws IncompletePayment
     */
    public function handle(): void
    {
        // Si il y a déja un abonnement ont le met a jours sur le nouveau prix et le nouveau produit
        if ($this->team->subscribed()) {

            $subscription = $this->team->subscription();
            $product = $this->team->getSubscriptionProduct();

            if ($product) {
                $this->team->unsetLimit();
            }

            $subscription->swap($this->product->stripe_price_id);

        } else {

            $this->team->newSubscription(
                'default', $this->product->stripe_price_id
            )->create($this->paymentMethodId);

        }

        $this->team->setLimit();
    }
}
