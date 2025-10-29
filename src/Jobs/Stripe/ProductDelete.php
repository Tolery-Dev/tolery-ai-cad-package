<?php

namespace Tolery\AiCad\Jobs\Stripe;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class ProductDelete implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected string $subscriptionProductStripeId)
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
        // On ne peut pas supprimer un produit avec un prix associé via l'API, on le va donc juste le désactiver.
        Cashier::stripe()
            ->products
            ->update($this->subscriptionProductStripeId, ['active' => false, 'metadata' => ['deleted' => 'true']]);
    }
}
