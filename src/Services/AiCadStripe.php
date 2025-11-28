<?php

namespace Tolery\AiCad\Services;

use Stripe\StripeClient;

/**
 * Service dédié pour les appels API Stripe AI-CAD.
 *
 * Utilise les clés Stripe spécifiques au package (AICAD_STRIPE_*)
 * pour supporter plusieurs comptes Stripe dans la même application.
 */
class AiCadStripe
{
    protected ?StripeClient $client = null;

    /**
     * Get the Stripe client configured with AI-CAD specific keys.
     */
    public function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient([
                'api_key' => $this->getSecretKey(),
                'stripe_version' => '2024-06-20',
            ]);
        }

        return $this->client;
    }

    /**
     * Get the AI-CAD Stripe public key.
     */
    public function getPublicKey(): string
    {
        return config('ai-cad.stripe.key', '');
    }

    /**
     * Get the AI-CAD Stripe secret key.
     */
    public function getSecretKey(): string
    {
        return config('ai-cad.stripe.secret', '');
    }

    /**
     * Get the AI-CAD Stripe webhook secret.
     */
    public function getWebhookSecret(): string
    {
        return config('ai-cad.stripe.webhook_secret', '');
    }

    /**
     * Create a PaymentIntent for a one-time file purchase.
     *
     * @param  int  $amount  Amount in cents
     * @param  string  $currency  Currency code (default: eur)
     * @param  array  $metadata  Additional metadata
     */
    public function createPaymentIntent(int $amount, string $currency = 'eur', array $metadata = []): \Stripe\PaymentIntent
    {
        return $this->client()->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => array_merge([
                'type' => 'file_purchase',
            ], $metadata),
        ]);
    }

    /**
     * Retrieve a PaymentIntent by ID.
     */
    public function retrievePaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Create a Checkout Session for subscription.
     *
     * @param  string  $priceId  Stripe Price ID
     * @param  string  $successUrl  URL to redirect after successful payment
     * @param  string  $cancelUrl  URL to redirect after cancelled payment
     * @param  array  $metadata  Additional metadata
     */
    public function createCheckoutSession(
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        ?string $customerId = null
    ): \Stripe\Checkout\Session {
        $params = [
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ];

        if ($customerId) {
            $params['customer'] = $customerId;
        }

        return $this->client()->checkout->sessions->create($params);
    }

    /**
     * Create a Billing Portal session for customer self-service.
     *
     * @param  string  $customerId  Stripe Customer ID
     * @param  string  $returnUrl  URL to return after portal session
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): \Stripe\BillingPortal\Session
    {
        return $this->client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Verify a webhook signature.
     *
     * @param  string  $payload  Raw request body
     * @param  string  $signature  Stripe-Signature header
     *
     * @throws \Stripe\Exception\SignatureVerificationException
     */
    public function verifyWebhookSignature(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $this->getWebhookSecret()
        );
    }

    /**
     * Get all active products from Stripe.
     */
    public function listProducts(int $limit = 100): \Stripe\Collection
    {
        return $this->client()->products->all([
            'active' => true,
            'limit' => $limit,
        ]);
    }

    /**
     * Get all prices for a product.
     */
    public function listPrices(string $productId, int $limit = 100): \Stripe\Collection
    {
        return $this->client()->prices->all([
            'product' => $productId,
            'active' => true,
            'limit' => $limit,
        ]);
    }

    /**
     * Create or update a Stripe customer for the team.
     */
    public function createOrUpdateCustomer(string $email, string $name, ?string $existingCustomerId = null): \Stripe\Customer
    {
        $customerData = [
            'email' => $email,
            'name' => $name,
        ];

        if ($existingCustomerId) {
            return $this->client()->customers->update($existingCustomerId, $customerData);
        }

        return $this->client()->customers->create($customerData);
    }
}
