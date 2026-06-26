<?php

namespace Tolery\AiCad\Services;

use Illuminate\Support\Facades\Log;
use Stripe\Price;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\Limit;
use Tolery\AiCad\Models\SubscriptionProduct;

class FileAccessService
{
    public function __construct(
        protected AiCadStripe $aiCadStripe
    ) {}

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
        // 1. Beta testeur → accès libre
        if ($team->isBetaTester()) {
            return [
                'can_download' => true,
                'reason' => 'beta_tester',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 2. Déjà téléchargé ce chat ?
        if (ChatDownload::isDownloaded($team, $chat)) {
            return [
                'can_download' => true,
                'reason' => 'already_downloaded',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 3. A acheté ce chat spécifiquement ?
        if (FilePurchase::hasPurchased($team, $chat)) {
            return [
                'can_download' => true,
                'reason' => 'purchased',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 4. Vérifie si abonné actif
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
                    'eligible_for_trial' => $this->isEligibleForTrial($team),
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

        /** @var Limit|null $limit */
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
                'eligible_for_trial' => false, // déjà abonné → pas d'essai
            ],
        ];
    }

    /**
     * Vérifie si une team peut télécharger une version spécifique (message) d'un chat
     *
     * @return array{
     *   can_download: bool,
     *   reason: string,
     *   remaining_quota: ?int,
     *   total_quota: ?int,
     *   options: array
     * }
     */
    public function canDownloadMessage(ChatTeam $team, Chat $chat, ChatMessage $message): array
    {
        // 1. Beta testeur → accès libre
        if ($team->isBetaTester()) {
            return [
                'can_download' => true,
                'reason' => 'beta_tester',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 2. Déjà téléchargé cette version ?
        if (ChatDownload::isMessageDownloaded($team, $chat, $message)) {
            return [
                'can_download' => true,
                'reason' => 'already_downloaded',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 3. A acheté ce chat spécifiquement ?
        if (FilePurchase::hasPurchased($team, $chat)) {
            return [
                'can_download' => true,
                'reason' => 'purchased',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [],
            ];
        }

        // 4. Vérifie si abonné actif
        if (! $team->subscribed()) {
            return [
                'can_download' => false,
                'reason' => 'no_subscription',
                'remaining_quota' => null,
                'total_quota' => null,
                'options' => [
                    'can_purchase' => true,
                    'can_subscribe' => true,
                    'purchase_price' => $this->getOneTimePurchasePrice(),
                    'eligible_for_trial' => $this->isEligibleForTrial($team),
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

        /** @var Limit|null $limit */
        $limit = $team->limits()
            ->where('subscription_product_id', $product->id)
            ->where('end_date', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $limit) {
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
                'eligible_for_trial' => false, // déjà abonné → pas d'essai
            ],
        ];
    }

    /**
     * Enregistre le téléchargement d'un chat
     * Crée le record + incrémente used_amount si abonné
     */
    public function recordChatDownload(ChatTeam $team, Chat $chat): void
    {
        // Un beta testeur a un accès libre permanent via la porte `beta_tester`
        // de canDownloadChat(). On ne matérialise donc pas de ChatDownload :
        // sinon cette trace « gratuite » (aucun quota consommé) rouvrirait
        // l'accès via la porte `already_downloaded` une fois le statut beta
        // révoqué (#2434).
        if ($team->isBetaTester()) {
            return;
        }

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
                /** @var Limit|null $limit */
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
     * Enregistre le téléchargement d'une version spécifique (message)
     */
    public function recordMessageDownload(ChatTeam $team, Chat $chat, ChatMessage $message): void
    {
        // Idem recordChatDownload : un beta testeur ne laisse pas de trace
        // ChatDownload, qui rouvrirait l'accès gratuit après révocation (#2434).
        if ($team->isBetaTester()) {
            return;
        }

        // Vérifie que cette version n'a pas déjà été téléchargée
        if (ChatDownload::isMessageDownloaded($team, $chat, $message)) {
            Log::info('Message version already downloaded, skipping record', [
                'team_id' => $team->id,
                'chat_id' => $chat->id,
                'message_id' => $message->id,
            ]);

            return;
        }

        // Crée le record de téléchargement pour cette version
        ChatDownload::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'downloaded_at' => now(),
        ]);

        // Si abonné, incrémente le compteur
        if ($team->subscribed()) {
            $product = $team->getSubscriptionProduct();

            if ($product) {
                /** @var Limit|null $limit */
                $limit = $team->limits()
                    ->where('subscription_product_id', $product->id)
                    ->where('end_date', '>', now())
                    ->orderByDesc('created_at')
                    ->first();

                if ($limit) {
                    $limit->used_amount += 1;
                    $limit->save();

                    Log::info('Incremented subscription quota for message download', [
                        'team_id' => $team->id,
                        'chat_id' => $chat->id,
                        'message_id' => $message->id,
                        'used_amount' => $limit->used_amount,
                        'files_allowed' => $product->files_allowed,
                    ]);
                }
            }
        }

        Log::info('Message version download recorded', [
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'message_id' => $message->id,
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

        /** @var Limit|null $limit */
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
     * Une équipe est éligible à l'essai gratuit de 30 jours si elle n'a jamais
     * eu d'abonnement ToleryCad (#2321).
     *
     * Cashier ne supprime jamais physiquement les abonnements : un abonnement
     * annulé reste en base avec `ends_at` renseigné, donc `exists()` suffit à
     * détecter un abonnement passé. Reflète la règle de
     * App\Http\Controllers\Client\ToleryCadSubscriptionController côté app hôte.
     */
    public function isEligibleForTrial(ChatTeam $team): bool
    {
        return ! $team->subscriptions()->exists();
    }

    /**
     * Récupère le prix du produit one-shot depuis Stripe
     * Le produit one-shot est identifié par files_allowed = 1
     *
     * @return int Prix en centimes
     */
    public function getOneTimePurchasePrice(): int
    {
        $price = $this->getOneTimePurchaseStripePrice();

        if ($price === null) {
            return (int) config('ai-cad.file_purchase_price', 999);
        }

        return $price->unit_amount ?? (int) config('ai-cad.file_purchase_price', 999);
    }

    /**
     * Récupère l'ID du prix Stripe du produit one-shot (files_allowed = 1).
     *
     * Utilisé par la Checkout Session one-shot : passer le Price ID existant
     * (plutôt qu'un prix inline) garde la facture rattachée au vrai produit Stripe.
     */
    public function getOneTimePurchasePriceId(): ?string
    {
        return $this->getOneTimePurchaseStripePrice()?->id;
    }

    /**
     * Récupère le premier prix actif du produit one-shot (files_allowed = 1).
     */
    private function getOneTimePurchaseStripePrice(): ?Price
    {
        try {
            $product = SubscriptionProduct::where('files_allowed', 1)
                ->where('active', true)
                ->first();

            if (! $product) {
                Log::warning('One-shot product not found');

                return null;
            }

            $prices = $this->aiCadStripe->listPrices($product->stripe_id, 1);

            if (empty($prices->data)) {
                Log::warning('No active price found for one-shot product', [
                    'product_id' => $product->id,
                    'stripe_id' => $product->stripe_id,
                ]);

                return null;
            }

            /** @var Price $price */
            $price = $prices->data[0];

            return $price;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve one-shot price from Stripe', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
