<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\Limit;
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

            // Créer l'enregistrement FilePurchase dans une transaction
            $purchase = DB::transaction(function () use ($team, $chat, $paymentIntent) {
                return FilePurchase::create([
                    'team_id' => $team->id,
                    'chat_id' => $chat->id,
                    'stripe_payment_intent_id' => $paymentIntent['id'],
                    'amount' => $paymentIntent['amount'],
                    'currency' => $paymentIntent['currency'],
                    'purchased_at' => now(),
                ]);
            });

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
     * This is called when Stripe creates the subscription (may happen before checkout.session.completed)
     */
    protected function handleCustomerSubscriptionCreated(array $payload): JsonResponse
    {
        try {
            $subscription = $payload['object'];

            Log::info('[AICAD Webhook] Subscription created event', [
                'subscription_id' => $subscription['id'] ?? null,
                'customer' => $subscription['customer'] ?? null,
                'status' => $subscription['status'] ?? null,
            ]);

            $teamModel = config('ai-cad.chat_team_model', ChatTeam::class);
            $team = $teamModel::where('tolerycad_stripe_id', $subscription['customer'])->first();

            if (! $team) {
                Log::warning('[AICAD Webhook] Team not found for customer', [
                    'customer_id' => $subscription['customer'],
                ]);

                return response()->json(['success' => true], 200);
            }

            $productStripeId = $subscription['items']['data'][0]['price']['product'] ?? null;
            $priceStripeId = $subscription['items']['data'][0]['price']['id'] ?? null;

            if (! $productStripeId) {
                Log::warning('[AICAD Webhook] No product found in subscription items');

                return response()->json(['success' => true], 200);
            }

            $subscriptionProduct = SubscriptionProduct::where('stripe_id', $productStripeId)->first();
            if (! $subscriptionProduct) {
                Log::warning('[AICAD Webhook] Subscription product not found', [
                    'stripe_product_id' => $productStripeId,
                ]);

                return response()->json(['success' => true], 200);
            }

            DB::transaction(function () use ($team, $subscriptionProduct, $subscription, $priceStripeId) {
                $stripeSubscription = $this->aiCadStripe->client()->subscriptions->retrieve($subscription['id']);

                $this->syncSubscriptionRecord($team, $stripeSubscription, $priceStripeId);

                $this->createOrUpdateLimitForTeam($team, $subscriptionProduct, $stripeSubscription);
            });

            Log::info('[AICAD Webhook] Subscription created and synced', [
                'team_id' => $team->id,
                'product_id' => $subscriptionProduct->id,
            ]);

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('[AICAD Webhook] Failed to handle customer.subscription.created', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Handle customer.subscription.updated webhook.
     * This is called on subscription updates, including:
     * - Billing period renewals
     * - Plan changes via Stripe Billing Portal
     * - Status changes (active, past_due, canceled, etc.)
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): JsonResponse
    {
        try {
            $subscription = $payload['object'];
            $previousAttributes = $payload['previous_attributes'] ?? [];

            Log::info('[AICAD Webhook] Subscription updated event', [
                'subscription_id' => $subscription['id'] ?? null,
                'status' => $subscription['status'] ?? null,
                'previous_attributes' => array_keys($previousAttributes),
            ]);

            // Find team
            $teamModel = config('ai-cad.chat_team_model', ChatTeam::class);
            $team = $teamModel::where('tolerycad_stripe_id', $subscription['customer'])->first();

            if (! $team) {
                Log::warning('[AICAD Webhook] Team not found for customer', [
                    'customer_id' => $subscription['customer'],
                ]);

                return response()->json(['success' => true], 200);
            }

            // Get current subscription product
            $productStripeId = $subscription['items']['data'][0]['price']['product'] ?? null;
            $priceStripeId = $subscription['items']['data'][0]['price']['id'] ?? null;

            if (! $productStripeId) {
                Log::warning('[AICAD Webhook] No product found in subscription items');

                return response()->json(['success' => true], 200);
            }

            $subscriptionProduct = SubscriptionProduct::where('stripe_id', $productStripeId)->first();
            if (! $subscriptionProduct) {
                Log::warning('[AICAD Webhook] Subscription product not found', [
                    'stripe_product_id' => $productStripeId,
                ]);

                return response()->json(['success' => true], 200);
            }

            DB::transaction(function () use ($team, $subscriptionProduct, $subscription, $priceStripeId, $previousAttributes) {
                $stripeSubscription = $this->aiCadStripe->client()->subscriptions->retrieve($subscription['id']);

                // Sync subscription record (handles plan changes in the subscription table)
                $this->syncSubscriptionRecord($team, $stripeSubscription, $priceStripeId);

                // Detect if this is a plan change (items changed)
                $isPlanChange = isset($previousAttributes['items']);

                if ($isPlanChange) {
                    Log::info('[AICAD Webhook] Plan change detected', [
                        'team_id' => $team->id,
                        'new_product_id' => $subscriptionProduct->id,
                    ]);
                }

                // Create or update limit for the current period
                // This handles both:
                // - New billing periods (creates new limit)
                // - Plan changes (updates existing limit's product)
                $this->createOrUpdateLimitForTeam($team, $subscriptionProduct, $stripeSubscription);
            });

            Log::info('[AICAD Webhook] Subscription updated successfully', [
                'team_id' => $team->id,
                'product_id' => $subscriptionProduct->id,
            ]);

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('[AICAD Webhook] Failed to handle customer.subscription.updated', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => true], 200);
        }
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
     *
     * Note: This webhook may arrive BEFORE or AFTER customer.subscription.created.
     * We use syncSubscriptionRecord() to handle both cases gracefully.
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

            // Get Stripe subscription details
            $stripeSubscription = $this->aiCadStripe->client()->subscriptions->retrieve(
                $stripeSubscriptionId
            );

            // Get subscription product - either from metadata or from Stripe subscription
            $subscriptionProduct = null;
            if ($subscriptionProductId) {
                $subscriptionProduct = SubscriptionProduct::find($subscriptionProductId);
            }
            if (! $subscriptionProduct) {
                $productStripeId = $stripeSubscription->items->data[0]->price->product ?? null;
                if ($productStripeId) {
                    $subscriptionProduct = SubscriptionProduct::where('stripe_id', $productStripeId)->first();
                }
            }

            // Create/update subscription and limit in an atomic transaction
            DB::transaction(function () use ($team, $session, $stripeSubscription, $subscriptionProduct, $teamId) {
                // Update team's ToleryCad customer ID
                $team->tolerycad_stripe_id = $session['customer'];
                $team->save();

                // Sync subscription record - handles duplicates and updates existing records
                $this->syncSubscriptionRecord($team, $stripeSubscription);

                // Create or update the limit for this subscription period
                if ($subscriptionProduct) {
                    $this->createOrUpdateLimitForTeam($team, $subscriptionProduct, $stripeSubscription);

                    Log::info('[AICAD Webhook] Limit synced for checkout subscription', [
                        'team_id' => $teamId,
                        'product_id' => $subscriptionProduct->id,
                    ]);
                }
            });

            Log::info('[AICAD Webhook] Subscription synced from checkout', [
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

    /**
     * Create a limit for a team based on their subscription
     *
     * @param  \Stripe\Subscription  $subscription  Subscription object with current_period_start and current_period_end properties
     */
    protected function createLimitForTeam(ChatTeam $team, SubscriptionProduct $product, \Stripe\Subscription $subscription): void
    {
        /** @phpstan-ignore-next-line */
        $currentPeriodStart = $subscription->current_period_start;
        /** @phpstan-ignore-next-line */
        $currentPeriodEnd = $subscription->current_period_end;

        $startDate = \Carbon\Carbon::createFromTimestamp($currentPeriodStart);
        $endDate = \Carbon\Carbon::createFromTimestamp($currentPeriodEnd);

        // Check if a limit already exists for this period
        $existingLimit = Limit::where('team_id', $team->id)
            ->where('subscription_product_id', $product->id)
            ->where('start_date', '<=', $startDate)
            ->where('end_date', '>=', $endDate)
            ->first();

        if ($existingLimit) {
            Log::info('[AICAD Webhook] Limit already exists for this period', [
                'limit_id' => $existingLimit->id,
                'team_id' => $team->id,
            ]);

            return;
        }

        // Count existing downloads in this period to initialize used_amount correctly
        $existingDownloads = ChatDownload::where('team_id', $team->id)
            ->whereBetween('downloaded_at', [$startDate, $endDate])
            ->count();

        $limit = new Limit;
        $limit->subscription_product_id = $product->id;
        $limit->team_id = $team->id;
        $limit->used_amount = $existingDownloads;
        $limit->start_date = $startDate;
        $limit->end_date = $endDate;
        $limit->save();

        Log::info('[AICAD Webhook] Limit created', [
            'limit_id' => $limit->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
            'used_amount' => $existingDownloads,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ]);
    }

    /**
     * Synchronize subscription record - update existing or create new one, and clean up duplicates.
     *
     * This method ensures only ONE subscription record exists for a team at a time.
     * When a plan is changed via the Stripe Billing Portal, Stripe keeps the same subscription ID
     * but the product changes. This method handles that by updating the existing record.
     */
    protected function syncSubscriptionRecord(ChatTeam $team, \Stripe\Subscription $stripeSubscription, ?string $priceStripeId = null): void
    {
        // Get price from Stripe subscription if not provided
        if (! $priceStripeId) {
            $priceStripeId = $stripeSubscription->items->data[0]->price->id ?? null;
        }

        // Find existing subscription by stripe_id (Stripe subscription ID remains the same even when plan changes)
        $existingSubscription = $team->subscriptions()
            ->where('stripe_id', $stripeSubscription->id)
            ->first();

        if ($existingSubscription) {
            /** @var \Laravel\Cashier\Subscription $existingSubscription */
            // Update existing subscription
            $existingSubscription->update([
                'stripe_status' => $stripeSubscription->status,
                'stripe_price' => $priceStripeId,
            ]);

            // Update subscription items
            foreach ($stripeSubscription->items->data as $item) {
                $existingSubscription->items()->updateOrCreate(
                    ['stripe_id' => $item->id],
                    [
                        'stripe_product' => $item->price->product,
                        'stripe_price' => $item->price->id,
                        'quantity' => $item->quantity ?? 1,
                    ]
                );
            }

            Log::info('[AICAD Webhook] Subscription record updated', [
                'team_id' => $team->id,
                'subscription_id' => $existingSubscription->id,
                'stripe_id' => $stripeSubscription->id,
            ]);

            return;
        }

        // No existing subscription with this stripe_id - check for OTHER active subscriptions to clean up
        // This handles the case where an old subscription exists with a different stripe_id
        $otherActiveSubscriptions = $team->subscriptions()
            ->where('stripe_id', '!=', $stripeSubscription->id)
            ->whereNull('ends_at')
            ->get();

        /** @var \Laravel\Cashier\Subscription $oldSubscription */
        foreach ($otherActiveSubscriptions as $oldSubscription) {
            // Mark old subscriptions as ended
            $oldSubscription->update(['ends_at' => now()]);

            Log::info('[AICAD Webhook] Old subscription marked as ended', [
                'team_id' => $team->id,
                'old_subscription_id' => $oldSubscription->id,
                /** @phpstan-ignore-next-line */
                'old_stripe_id' => $oldSubscription->stripe_id,
            ]);
        }

        // Create new subscription record
        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $team->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $priceStripeId,
            'quantity' => 1,
            'ends_at' => null,
        ]);

        // Create subscription items
        foreach ($stripeSubscription->items->data as $item) {
            $subscription->items()->create([
                'stripe_id' => $item->id,
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? 1,
            ]);
        }

        Log::info('[AICAD Webhook] New subscription record created', [
            'team_id' => $team->id,
            'subscription_id' => $subscription->id,
            'stripe_id' => $stripeSubscription->id,
        ]);
    }

    /**
     * Create or update a limit for a team based on their subscription.
     *
     * This method handles plan changes by updating the existing limit's subscription_product_id
     * instead of creating duplicate limits for the same period.
     */
    protected function createOrUpdateLimitForTeam(ChatTeam $team, SubscriptionProduct $product, \Stripe\Subscription $stripeSubscription): void
    {
        /** @phpstan-ignore-next-line */
        $currentPeriodStart = $stripeSubscription->current_period_start;
        /** @phpstan-ignore-next-line */
        $currentPeriodEnd = $stripeSubscription->current_period_end;

        $startDate = \Carbon\Carbon::createFromTimestamp($currentPeriodStart);
        $endDate = \Carbon\Carbon::createFromTimestamp($currentPeriodEnd);

        // First, check if a limit already exists for this EXACT product and period
        $existingLimitForProduct = Limit::where('team_id', $team->id)
            ->where('subscription_product_id', $product->id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();

        if ($existingLimitForProduct) {
            Log::info('[AICAD Webhook] Limit already exists for this product and period', [
                'limit_id' => $existingLimitForProduct->id,
                'team_id' => $team->id,
                'product_id' => $product->id,
            ]);

            return;
        }

        // Check if there's a limit for a DIFFERENT product in the same period (plan change)
        $existingLimitForPeriod = Limit::where('team_id', $team->id)
            ->where('subscription_product_id', '!=', $product->id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();

        if ($existingLimitForPeriod) {
            // Plan changed - update the existing limit's product (preserve used_amount)
            $oldProductId = $existingLimitForPeriod->subscription_product_id;
            $existingLimitForPeriod->subscription_product_id = $product->id;
            $existingLimitForPeriod->save();

            Log::info('[AICAD Webhook] Limit updated for plan change', [
                'limit_id' => $existingLimitForPeriod->id,
                'team_id' => $team->id,
                'old_product_id' => $oldProductId,
                'new_product_id' => $product->id,
                'used_amount_preserved' => $existingLimitForPeriod->used_amount,
            ]);

            return;
        }

        // No existing limit for this period - create a new one
        // Count existing downloads in this period to initialize used_amount correctly
        $existingDownloads = ChatDownload::where('team_id', $team->id)
            ->whereBetween('downloaded_at', [$startDate, $endDate])
            ->count();

        $limit = new Limit;
        $limit->subscription_product_id = $product->id;
        $limit->team_id = $team->id;
        $limit->used_amount = $existingDownloads;
        $limit->start_date = $startDate;
        $limit->end_date = $endDate;
        $limit->save();

        Log::info('[AICAD Webhook] Limit created', [
            'limit_id' => $limit->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
            'used_amount' => $existingDownloads,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ]);
    }
}
