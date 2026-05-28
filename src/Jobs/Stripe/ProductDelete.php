<?php

namespace Tolery\AiCad\Jobs\Stripe;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

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
        try {
            // On ne peut pas supprimer un produit avec un prix associé via l'API, on le va donc juste le désactiver.
            Cashier::stripe()
                ->products
                ->update($this->subscriptionProductStripeId, ['active' => false, 'metadata' => ['deleted' => 'true']]);
        } catch (InvalidRequestException $e) {
            if (! ProductUpdate::isStripeMissingResource($e)) {
                throw $e;
            }

            // Product already gone on Stripe — desired end state, no-op.
            Log::info('[AiCad] ProductDelete skipped — Stripe product already missing', [
                'stripe_id' => $this->subscriptionProductStripeId,
            ]);
        }
    }
}
