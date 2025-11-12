<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
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

    public function handle(): void
    {
        $isUpdate = $this->team->subscribed();

        if ($isUpdate) {
            $this->updateExistingSubscription();
        } else {
            $this->createNewSubscription();
        }

        $this->team->refresh()->setLimit();

        Log::info('Subscription processed successfully', [
            'team_id' => $this->team->id,
            'product_id' => $this->product?->id,
            'is_update' => $isUpdate,
        ]);
    }

    protected function updateExistingSubscription(): void
    {
        $subscription = $this->team->subscription();
        $subscription->loadMissing('owner');
        
        $product = $this->team->getSubscriptionProduct();

        if ($product) {
            $this->team->unsetLimit();
        }

        if ($this->paymentMethodId) {
            $subscription->updateStripeSubscription([
                'default_payment_method' => $this->paymentMethodId,
            ]);
        }

        if ($this->stripePriceId) {
            $subscription->swap($this->stripePriceId);
        }
    }

    protected function createNewSubscription(): void
    {
        if (! $this->stripePriceId || ! $this->paymentMethodId) {
            throw new InvalidRequestException(
                'Price ID and Payment Method ID are required to create a new subscription'
            );
        }

        $this->team->newSubscription('default', $this->stripePriceId)
            ->create($this->paymentMethodId);
    }
}
