<?php

namespace Tolery\AiCad\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Tolery\AiCad\Services\AiCadStripe;

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

        // Notification de succès via Flux toast
        $this->js("Flux.toast({ heading: 'Paiement réussi !', text: 'Vous pouvez maintenant télécharger votre fichier.', variant: 'success' })");

        // Fermer le modal après 2 secondes
        $this->js('setTimeout(() => { $wire.closeModal() }, 2000)');
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

    public function render()
    {
        return view('ai-cad::livewire.stripe-payment-modal');
    }
}
