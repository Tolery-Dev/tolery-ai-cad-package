<?php

namespace Tolery\AiCad\Services;

use Stripe\Checkout\Session;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\PromotionCode;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

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
    public function createPaymentIntent(int $amount, string $currency = 'eur', array $metadata = []): PaymentIntent
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
    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
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
        ?string $customerId = null,
        ?string $promotionCodeId = null
    ): Session {
        $params = [
            'mode' => 'subscription',
            'locale' => 'fr',
            'billing_address_collection' => 'required',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'consent_collection' => [
                'terms_of_service' => 'required',
            ],
        ];

        if ($customerId) {
            $params['customer'] = $customerId;
        }

        if ($promotionCodeId) {
            $params['discounts'] = [['promotion_code' => $promotionCodeId]];
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
     * @throws SignatureVerificationException
     */
    public function verifyWebhookSignature(string $payload, string $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            $this->getWebhookSecret()
        );
    }

    /**
     * Get all active products from Stripe.
     */
    public function listProducts(int $limit = 100): Collection
    {
        return $this->client()->products->all([
            'active' => true,
            'limit' => $limit,
        ]);
    }

    /**
     * Get all prices for a product.
     */
    public function listPrices(string $productId, int $limit = 100): Collection
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
    public function createOrUpdateCustomer(string $email, string $name, ?string $existingCustomerId = null): Customer
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

    /**
     * Create a Stripe promotion code linked to a coupon.
     *
     * @param  string  $couponId  The parent coupon ID
     * @param  string  $code  The human-readable promotion code
     * @param  array  $metadata  Additional metadata
     */
    public function createPromotionCode(string $couponId, string $code, array $metadata = []): PromotionCode
    {
        return $this->client()->promotionCodes->create([
            'coupon' => $couponId,
            'code' => $code,
            'max_redemptions' => 1,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Deactivate a Stripe promotion code.
     */
    public function deactivatePromotionCode(string $promotionCodeId): PromotionCode
    {
        return $this->client()->promotionCodes->update($promotionCodeId, [
            'active' => false,
        ]);
    }

    /**
     * Retrieve a Stripe subscription by ID with discount info.
     */
    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->client()->subscriptions->retrieve($subscriptionId, [
            'expand' => ['discount', 'discount.promotion_code'],
        ]);
    }

    /**
     * List active subscriptions for a customer.
     */
    public function listCustomerSubscriptions(string $customerId): Collection
    {
        return $this->client()->subscriptions->all([
            'customer' => $customerId,
            'status' => 'active',
            'expand' => ['data.discount', 'data.discount.promotion_code'],
        ]);
    }
}
