<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\AiCadStripe;

/**
 * Webhook controller for AI-CAD Stripe events.
 *
 * This controller is independent of Laravel Cashier and uses
 * the AI-CAD specific Stripe keys (AICAD_STRIPE_*).
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        protected AiCadStripe $aiCadStripe
    ) {}

    /**
     * Handle incoming Stripe webhook requests.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            Log::warning('[AICAD Webhook] Missing Stripe-Signature header');

            return response()->json(['error' => 'Missing signature'], 400);
        }

        try {
            $event = $this->aiCadStripe->verifyWebhookSignature($payload, $signature);
        } catch (SignatureVerificationException $e) {
            Log::warning('[AICAD Webhook] Invalid signature', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $method = 'handle'.str_replace('.', '', ucwords(str_replace('_', '.', $event->type), '.'));

        if (method_exists($this, $method)) {
            return $this->{$method}($event->data->toArray());
        }

        Log::info('[AICAD Webhook] Unhandled event type', ['type' => $event->type]);

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle payment_intent.succeeded webhook.
     */
    protected function handlePaymentIntentSucceeded(array $payload): JsonResponse
    {
        try {
            $paymentIntent = $payload['object'];
            $metadata = $paymentIntent['metadata'] ?? [];

            // Vérifier que c'est un achat de fichier AI-CAD
            if (($metadata['type'] ?? null) !== 'file_purchase') {
                Log::info('[AICAD Webhook] Payment intent is not a file purchase, skipping', [
                    'payment_intent_id' => $paymentIntent['id'],
                    'type' => $metadata['type'] ?? 'unknown',
                ]);

                return response()->json(['success' => true], 200);
            }

            $teamId = $metadata['team_id'] ?? null;
            $chatId = $metadata['chat_id'] ?? null;

            if (! $teamId || ! $chatId) {
                Log::error('[AICAD Webhook] Missing required metadata in payment intent', [
                    'payment_intent_id' => $paymentIntent['id'],
                    'metadata' => $metadata,
                ]);

                return response()->json(['success' => true], 200);
            }

            // Vérifier que la team et le chat existent
            $teamModel = config('ai-cad.chat_team_model', ChatTeam::class);
            $team = $teamModel::find($teamId);
            $chat = Chat::find($chatId);

            if (! $team || ! $chat) {
                Log::error('[AICAD Webhook] Team or chat not found', [
                    'team_id' => $teamId,
                    'chat_id' => $chatId,
                    'payment_intent_id' => $paymentIntent['id'],
                ]);

                return response()->json(['success' => true], 200);
            }

            // Vérifier que l'achat n'existe pas déjà (éviter les doublons)
            $existingPurchase = FilePurchase::where('stripe_payment_intent_id', $paymentIntent['id'])->first();

            if ($existingPurchase) {
                Log::warning('[AICAD Webhook] File purchase already exists for this payment intent', [
                    'payment_intent_id' => $paymentIntent['id'],
                    'purchase_id' => $existingPurchase->id,
                ]);

                return response()->json(['success' => true], 200);
            }

            // Créer l'enregistrement FilePurchase
            $purchase = FilePurchase::create([
                'team_id' => $team->id,
                'chat_id' => $chat->id,
                'stripe_payment_intent_id' => $paymentIntent['id'],
                'amount' => $paymentIntent['amount'],
                'currency' => $paymentIntent['currency'],
                'purchased_at' => now(),
            ]);

            Log::info('[AICAD Webhook] File purchase created successfully', [
                'purchase_id' => $purchase->id,
                'team_id' => $team->id,
                'chat_id' => $chat->id,
                'amount' => $paymentIntent['amount'],
                'payment_intent_id' => $paymentIntent['id'],
            ]);

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('[AICAD Webhook] Failed to handle payment_intent.succeeded', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            // Retourner un succès pour éviter que Stripe ne réessaie indéfiniment
            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Handle customer.subscription.created webhook.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): JsonResponse
    {
        Log::info('[AICAD Webhook] Subscription created', [
            'subscription_id' => $payload['object']['id'] ?? null,
            'customer' => $payload['object']['customer'] ?? null,
        ]);

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle customer.subscription.updated webhook.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): JsonResponse
    {
        Log::info('[AICAD Webhook] Subscription updated', [
            'subscription_id' => $payload['object']['id'] ?? null,
            'status' => $payload['object']['status'] ?? null,
        ]);

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle customer.subscription.deleted webhook.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): JsonResponse
    {
        Log::info('[AICAD Webhook] Subscription deleted', [
            'subscription_id' => $payload['object']['id'] ?? null,
        ]);

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle invoice.paid webhook.
     */
    protected function handleInvoicePaid(array $payload): JsonResponse
    {
        Log::info('[AICAD Webhook] Invoice paid', [
            'invoice_id' => $payload['object']['id'] ?? null,
            'subscription' => $payload['object']['subscription'] ?? null,
        ]);

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle invoice.payment_failed webhook.
     */
    protected function handleInvoicePaymentFailed(array $payload): JsonResponse
    {
        Log::warning('[AICAD Webhook] Invoice payment failed', [
            'invoice_id' => $payload['object']['id'] ?? null,
            'subscription' => $payload['object']['subscription'] ?? null,
        ]);

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle checkout.session.completed webhook.
     *
     * This is triggered when a Stripe Checkout session is completed successfully.
     * Creates the subscription record in the database.
     */
    protected function handleCheckoutSessionCompleted(array $payload): JsonResponse
    {
        try {
            $session = $payload['object'];

            // Get metadata from checkout session
            $teamId = $session['metadata']['team_id'] ?? null;
            $subscriptionProductId = $session['metadata']['subscription_product_id'] ?? null;
            $stripeSubscriptionId = $session['subscription'] ?? null;

            if (! $teamId || ! $stripeSubscriptionId) {
                Log::warning('[AICAD Webhook] Missing team_id or subscription in checkout.session.completed', [
                    'team_id' => $teamId,
                    'subscription' => $stripeSubscriptionId,
                    'session_id' => $session['id'] ?? null,
                ]);

                return response()->json(['success' => true], 200);
            }

            // Get team model from config
            $teamModel = config('ai-cad.chat_team_model', ChatTeam::class);
            $team = $teamModel::find($teamId);

            if (! $team) {
                Log::error('[AICAD Webhook] Team not found', ['team_id' => $teamId]);

                return response()->json(['success' => true], 200);
            }

            // Check if subscription already exists (avoid duplicates)
            $existingSubscription = $team->subscriptions()->where('stripe_id', $stripeSubscriptionId)->first();
            if ($existingSubscription) {
                Log::info('[AICAD Webhook] Subscription already exists', [
                    'team_id' => $teamId,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                ]);

                return response()->json(['success' => true], 200);
            }

            // Get Stripe subscription details
            $stripeSubscription = $this->aiCadStripe->client()->subscriptions->retrieve(
                $stripeSubscriptionId
            );

            // Update team's ToleryCad customer ID
            $team->tolerycad_stripe_id = $session['customer'];
            $team->save();

            // Create subscription record using Cashier pattern
            $subscription = $team->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => $stripeSubscription->id,
                'stripe_status' => $stripeSubscription->status,
                'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                'quantity' => 1,
                'ends_at' => null,
            ]);

            // Create subscription items (required for getSubscriptionProduct to work)
            foreach ($stripeSubscription->items->data as $item) {
                $subscription->items()->create([
                    'stripe_id' => $item->id,
                    'stripe_product' => $item->price->product,
                    'stripe_price' => $item->price->id,
                    'quantity' => $item->quantity ?? 1,
                ]);
            }

            // Set subscription product limits if needed
            if ($subscriptionProductId) {
                $product = SubscriptionProduct::find($subscriptionProductId);
                if ($product && method_exists($team, 'setSubscriptionLimits')) {
                    $team->setSubscriptionLimits($product);
                }
            }

            Log::info('[AICAD Webhook] Subscription created from checkout', [
                'team_id' => $teamId,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_customer_id' => $session['customer'],
            ]);

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('[AICAD Webhook] Failed to handle checkout.session.completed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            // Return success to avoid Stripe retrying indefinitely
            return response()->json(['success' => true], 200);
        }
    }
}
