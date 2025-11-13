<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\ChatTeam;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle payment_intent.succeeded webhook
     *
     * @param array $payload
     * @return JsonResponse
     */
    public function handlePaymentIntentSucceeded(array $payload): JsonResponse
    {
        try {
            $paymentIntent = $payload['data']['object'];
            $metadata = $paymentIntent['metadata'] ?? [];

            // Vérifier que c'est un achat de fichier
            if (($metadata['type'] ?? null) !== 'file_purchase') {
                Log::info('[AICAD] Payment intent is not a file purchase, skipping', [
                    'payment_intent_id' => $paymentIntent['id'],
                    'type' => $metadata['type'] ?? 'unknown',
                ]);
                
                return response()->json(['success' => true], 200);
            }

            $teamId = $metadata['team_id'] ?? null;
            $chatId = $metadata['chat_id'] ?? null;

            if (! $teamId || ! $chatId) {
                Log::error('[AICAD] Missing required metadata in payment intent', [
                    'payment_intent_id' => $paymentIntent['id'],
                    'metadata' => $metadata,
                ]);
                
                return response()->json(['success' => true], 200);
            }

            // Vérifier que la team et le chat existent
            $team = ChatTeam::find($teamId);
            $chat = Chat::find($chatId);

            if (! $team || ! $chat) {
                Log::error('[AICAD] Team or chat not found', [
                    'team_id' => $teamId,
                    'chat_id' => $chatId,
                    'payment_intent_id' => $paymentIntent['id'],
                ]);
                
                return response()->json(['success' => true], 200);
            }

            // Vérifier que l'achat n'existe pas déjà (éviter les doublons)
            $existingPurchase = FilePurchase::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
            
            if ($existingPurchase) {
                Log::warning('[AICAD] File purchase already exists for this payment intent', [
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

            Log::info('[AICAD] File purchase created successfully', [
                'purchase_id' => $purchase->id,
                'team_id' => $team->id,
                'chat_id' => $chat->id,
                'amount' => $paymentIntent['amount'],
                'payment_intent_id' => $paymentIntent['id'],
            ]);

            // TODO: Optionnel - Envoyer un email de confirmation
            // Mail::to($team->owner)->send(new FilePurchaseConfirmation($purchase));

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('[AICAD] Failed to handle payment_intent.succeeded webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            // Retourner un succès pour éviter que Stripe ne réessaie indéfiniment
            // mais logger l'erreur pour investigation
            return response()->json(['success' => true], 200);
        }
    }
}
