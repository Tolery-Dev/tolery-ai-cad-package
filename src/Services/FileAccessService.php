<?php

namespace Tolery\AiCad\Services;

use Illuminate\Support\Facades\Log;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;

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
                    'purchase_price' => config('ai-cad.file_purchase_price', 999), // 9.99€ par défaut
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
                'purchase_price' => config('ai-cad.file_purchase_price', 999),
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

        $limit = $team->limits()
            ->where('subscription_product_id', $product->id)
            ->where('end_date', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $limit) {
            return null;
        }

        return [
            'used' => (int) $limit->used_amount,
            'total' => $product->files_allowed,
            'remaining' => max(0, $product->files_allowed - (int) $limit->used_amount),
            'period_end' => $limit->end_date,
        ];
    }
}
