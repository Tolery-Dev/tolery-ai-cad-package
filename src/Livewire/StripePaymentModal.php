<?php

namespace Tolery\AiCad\Livewire;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Services\AiCadStripe;
use Tolery\AiCad\Services\ZipGeneratorService;

class StripePaymentModal extends Component
{
    public bool $showModal = false;

    public ?string $clientSecret = null;

    public ?int $amount = null;

    public ?int $chatId = null;

    public ?string $screenshotUrl = null;

    public bool $processing = false;

    public bool $paymentSuccess = false;

    public ?string $errorMessage = null;

    /**
     * Get the Stripe public key for AI-CAD.
     */
    public function getStripeKeyProperty(): string
    {
        return app(AiCadStripe::class)->getPublicKey();
    }

    #[On('show-stripe-payment-modal')]
    public function showPaymentModal(string $clientSecret, int $amount, int $chatId, ?string $screenshotUrl = null): void
    {
        $this->clientSecret = $clientSecret;
        $this->amount = $amount;
        $this->chatId = $chatId;
        $this->screenshotUrl = $screenshotUrl;
        $this->showModal = true;
        $this->processing = false;
        $this->paymentSuccess = false;
        $this->errorMessage = null;

        Log::info('[AICAD] Stripe payment modal opened', [
            'chat_id' => $chatId,
            'amount' => $amount,
        ]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['clientSecret', 'amount', 'chatId', 'screenshotUrl', 'processing', 'paymentSuccess', 'errorMessage']);
    }

    public function handlePaymentSuccess(string $paymentIntentId): void
    {
        $this->paymentSuccess = true;
        $this->processing = false;

        Log::info('[AICAD] Payment completed successfully', [
            'chat_id' => $this->chatId,
            'payment_intent_id' => $paymentIntentId,
        ]);

        // Créer l'enregistrement FilePurchase si on est en environnement local/preprod
        // (le webhook ne peut pas être appelé)
        try {
            /** @var ChatUser $user */
            $user = auth()->user();
            $team = $user->team;

            if ($team && $this->chatId) {
                // Vérifier que l'achat n'existe pas déjà
                $existingPurchase = FilePurchase::where('stripe_payment_intent_id', $paymentIntentId)->first();

                if (! $existingPurchase) {
                    $purchase = FilePurchase::create([
                        'team_id' => $team->id,
                        'chat_id' => $this->chatId,
                        'stripe_payment_intent_id' => $paymentIntentId,
                        'amount' => $this->amount,
                        'currency' => 'eur',
                        'purchased_at' => now(),
                    ]);

                    Log::info('[AICAD] FilePurchase created after payment confirmation', [
                        'purchase_id' => $purchase->id,
                        'team_id' => $team->id,
                        'chat_id' => $this->chatId,
                        'payment_intent_id' => $paymentIntentId,
                    ]);
                } else {
                    Log::info('[AICAD] FilePurchase already exists', [
                        'purchase_id' => $existingPurchase->id,
                        'payment_intent_id' => $paymentIntentId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('[AICAD] Failed to create FilePurchase after payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_intent_id' => $paymentIntentId,
            ]);
        }

        // Rafraîchir le composant parent pour mettre à jour l'état de téléchargement
        $this->dispatch('payment-completed');
    }

    public function handlePaymentError(string $error): void
    {
        $this->errorMessage = $error;
        $this->processing = false;

        Log::error('[AICAD] Payment failed', [
            'chat_id' => $this->chatId,
            'error' => $error,
        ]);
    }

    /**
     * Download files after successful purchase.
     * This can be called multiple times since the file was purchased.
     */
    public function downloadAfterPurchase(): void
    {
        if (! $this->chatId) {
            Log::error('[AICAD] No chat ID for download after purchase');

            return;
        }

        $chat = Chat::find($this->chatId);

        if (! $chat) {
            Log::error('[AICAD] Chat not found for download', ['chat_id' => $this->chatId]);

            return;
        }

        Log::info('[AICAD] Generating ZIP after purchase', ['chat_id' => $this->chatId]);

        // Génère le ZIP avec tous les fichiers
        $zipService = app(ZipGeneratorService::class);
        $result = $zipService->generateChatFilesZip($chat);

        if (! $result['success']) {
            Log::error('[AICAD] ZIP generation failed after purchase', ['error' => $result['error']]);
            $this->js("Flux.toast({ heading: 'Erreur', text: '{$result['error']}', variant: 'danger' })");

            return;
        }

        // Stocker le ZIP dans un emplacement accessible
        $publicPath = 'downloads/'.basename($result['path']);
        Storage::disk('public')->put($publicPath, file_get_contents($result['path']));

        // Supprimer le fichier temporaire
        @unlink($result['path']);

        // Déclenche le téléchargement via JavaScript
        $downloadUrl = Storage::disk('public')->url($publicPath);
        $filename = $result['filename'];

        Log::info('[AICAD] Triggering download after purchase', [
            'url' => $downloadUrl,
            'filename' => $filename,
        ]);

        // Utiliser $this->js() pour déclencher directement le téléchargement
        $this->js("
            (function() {
                const link = document.createElement('a');
                link.href = '{$downloadUrl}';
                link.download = '{$filename}';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            })();
            Flux.toast({ heading: 'Téléchargement lancé', text: 'Votre archive est en cours de téléchargement.', variant: 'success' });
        ");
    }

    public function render()
    {
        return view('ai-cad::livewire.stripe-payment-modal');
    }
}
