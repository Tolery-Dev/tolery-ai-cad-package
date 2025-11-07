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

    public function __construct(
        public ChatTeam $team,
        public ?SubscriptionProduct $product,
        public ?string $stripePriceId,
        public ?string $paymentMethodId
    ) {}

    /**
     * @throws Throwable
     * @throws IncompletePayment
     */
    public function handle(): void
    {
        // Si il y a déja un abonnement ont le met a jours sur le nouveau prix et le nouveau produit voir le moyen de paiement
        if ($this->team->subscribed()) {

            $subscription = $this->team->subscription();
            $product = $this->team->getSubscriptionProduct();

            if ($product) {
                $this->team->unsetLimit();
            }

            // Mettre à jour le moyen de paiement pour l'abonnement
            if ($this->paymentMethodId) {
                $subscription->updateStripeSubscription([
                    'default_payment_method' => $this->paymentMethodId,
                ]);
            }

            if ($this->stripePriceId) {
                $subscription->swap($this->stripePriceId);
            }

        } else {

            if ($this->stripePriceId && $this->paymentMethodId) {
                $this->team->newSubscription(
                    'default', $this->stripePriceId
                )->create($this->paymentMethodId);
            }

        }

        $this->team->refresh()->setLimit();
    }
}
