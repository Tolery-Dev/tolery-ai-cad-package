<?php

namespace Tolery\AiCad\Livewire;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
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

    public function handlePaymentSuccess(): void
    {
        $this->paymentSuccess = true;
        $this->processing = false;

        Log::info('[AICAD] Payment completed successfully', [
            'chat_id' => $this->chatId,
        ]);

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

        // Dispatch un événement JavaScript pour déclencher le téléchargement
        $downloadUrl = Storage::disk('public')->url($publicPath);

        Log::info('[AICAD] Dispatching download after purchase', [
            'url' => $downloadUrl,
            'filename' => $result['filename'],
        ]);

        $this->dispatch('start-file-download', url: $downloadUrl, filename: $result['filename']);

        $this->js("Flux.toast({ heading: 'Téléchargement lancé', text: 'Votre archive est en cours de téléchargement.', variant: 'success' })");
    }

    public function render()
    {
        return view('ai-cad::livewire.stripe-payment-modal');
    }
}
