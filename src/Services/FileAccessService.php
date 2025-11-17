<?php

namespace Tolery\AiCad\Services;

use Illuminate\Support\Facades\Log;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\SubscriptionProduct;

class FileAccessService
{
    /**
     * Vérifie si une team peut télécharger les fichiers d'un chat
     *
     * Règle métier: 1 chat = 1 fichier
     *
     * @return array{
     *   can_download: bool,
     *   reason: string,
     *   remaining_quota: ?int,
     *   total_quota: ?int,
     *   options: array
     * }
     */
    public function canDownloadChat(ChatTeam $team, Chat $chat): array
    {
        // 1. Déjà téléchargé ce chat ?
        if (ChatDownload::isDownloaded($team, $chat)) {
            return [
                'can_download' => true,
                'reason' => 'already_downloaded',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 2. A acheté ce chat spécifiquement ?
        if (FilePurchase::hasPurchased($team, $chat)) {
            return [
                'can_download' => true,
                'reason' => 'purchased',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 3. Vérifie si abonné actif
        if (! $team->subscribed()) {
            return [
                'can_download' => false,
                'reason' => 'no_subscription',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [
                    'can_purchase' => true,
                    'can_subscribe' => true,
                    'purchase_price' => $this->getOneTimePurchasePrice(), // 9.99€ par défaut
                ],
            ];
        }

        // 4. Abonné: vérifie le quota
        $product = $team->getSubscriptionProduct();

        if (! $product) {
            Log::warning('Team subscribed but no product found', ['team_id' => $team->id]);

            return [
                'can_download' => false,
                'reason' => 'no_product',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [
                    'can_purchase' => true,
                    'can_subscribe' => false,
                ],
            ];
        }

        /** @var \Tolery\AiCad\Models\Limit|null $limit */
        $limit = $team->limits()
            ->where('subscription_product_id', $product->id)
            ->where('end_date', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $limit) {
            // Abonné mais pas de limite active (peut arriver si expirée)
            return [
                'can_download' => false,
                'reason' => 'no_active_limit',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [
                    'can_purchase' => true,
                    'can_subscribe' => false,
                ],
            ];
        }

        $filesAllowed = $product->files_allowed;
        $filesUsed = (int) $limit->used_amount;

        // Vérifier si le plan est illimité (files_allowed = -1)
        if ($filesAllowed === -1) {
            return [
                'can_download' => true,
                'reason' => 'subscription_unlimited',
                'remaining_quota' => null,
                'total_quota' => -1,
                'options' => [],
            ];
        }

        $remaining = max(0, $filesAllowed - $filesUsed);

        if ($remaining > 0) {
            return [
                'can_download' => true,
                'reason' => 'subscription_with_quota',
                'remaining_quota' => $remaining,
                'total_quota' => $filesAllowed,
                'options' => [],
            ];
        }

        // Quota épuisé
        return [
            'can_download' => false,
            'reason' => 'quota_exceeded',
            'remaining_quota' => 0,
            'total_quota' => $filesAllowed,
            'options' => [
                'can_purchase' => true,
                'can_subscribe' => true, // peut upgrade son plan
                'purchase_price' => $this->getOneTimePurchasePrice(),
            ],
        ];
    }

    /**
     * Enregistre le téléchargement d'un chat
     * Crée le record + incrémente used_amount si abonné
     */
    public function recordChatDownload(ChatTeam $team, Chat $chat): void
    {
        // Vérifie qu'il n'a pas déjà été téléchargé
        if (ChatDownload::isDownloaded($team, $chat)) {
            Log::info('Chat already downloaded, skipping record', [
                'team_id' => $team->id,
                'chat_id' => $chat->id,
            ]);

            return;
        }

        // Crée le record de téléchargement
        ChatDownload::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'downloaded_at' => now(),
        ]);

        // Si abonné, incrémente le compteur
        if ($team->subscribed()) {
            $product = $team->getSubscriptionProduct();

            if ($product) {
                /** @var \Tolery\AiCad\Models\Limit|null $limit */
                $limit = $team->limits()
                    ->where('subscription_product_id', $product->id)
                    ->where('end_date', '>', now())
                    ->orderByDesc('created_at')
                    ->first();

                if ($limit) {
                    $limit->used_amount += 1;
                    $limit->save();

                    Log::info('Incremented subscription quota', [
                        'team_id' => $team->id,
                        'chat_id' => $chat->id,
                        'used_amount' => $limit->used_amount,
                        'files_allowed' => $product->files_allowed,
                    ]);
                }
            }
        }

        Log::info('Chat download recorded', [
            'team_id' => $team->id,
            'chat_id' => $chat->id,
        ]);
    }

    /**
     * Obtient le statut du quota pour affichage UI
     */
    public function getQuotaStatus(ChatTeam $team): ?array
    {
        if (! $team->subscribed()) {
            return null;
        }

        $product = $team->getSubscriptionProduct();

        if (! $product) {
            return null;
        }

        /** @var \Tolery\AiCad\Models\Limit|null $limit */
        $limit = $team->limits()
            ->where('subscription_product_id', $product->id)
            ->where('end_date', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $limit) {
            return null;
        }

        // Gérer les plans illimités (files_allowed = -1)
        if ($product->files_allowed === -1) {
            return [
                'used' => (int) $limit->used_amount,
                'total' => -1,
                'remaining' => -1,  // -1 indique illimité
                'period_end' => $limit->end_date,
            ];
        }

        return [
            'used' => (int) $limit->used_amount,
            'total' => $product->files_allowed,
            'remaining' => max(0, $product->files_allowed - (int) $limit->used_amount),
            'period_end' => $limit->end_date,
        ];
    }

    /**
     * Récupère le prix du produit one-shot depuis Stripe
     * Le produit one-shot est identifié par files_allowed = 1
     *
     * @return int Prix en centimes
     */
    public function getOneTimePurchasePrice(): int
    {
        try {
            // Chercher le produit one-shot (files_allowed = 1)
            $product = SubscriptionProduct::where('files_allowed', 1)
                ->where('active', true)
                ->first();

            if (! $product) {
                Log::warning('One-shot product not found, using default price');

                return config('ai-cad.file_purchase_price', 999);
            }

            // Récupérer le prix depuis Stripe API
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            $stripeProduct = $stripe->products->retrieve($product->stripe_id);

            // Récupérer le premier prix actif
            $prices = $stripe->prices->all([
                'product' => $product->stripe_id,
                'active' => true,
                'limit' => 1,
            ]);

            if (empty($prices->data)) {
                Log::warning('No active price found for one-shot product', [
                    'product_id' => $product->id,
                    'stripe_id' => $product->stripe_id,
                ]);

                return config('ai-cad.file_purchase_price', 999);
            }

            $price = $prices->data[0];

            Log::info('Retrieved one-shot price from Stripe', [
                'product_id' => $product->id,
                'price' => $price->unit_amount,
                'currency' => $price->currency,
            ]);

            return $price->unit_amount;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve one-shot price from Stripe', [
                'error' => $e->getMessage(),
            ]);

            return config('ai-cad.file_purchase_price', 999);
        }
    }
}
