<div>
    {{-- Modal de paiement Stripe --}}
    <flux:modal name="stripe-payment" :open="$showModal" wire:model="showModal" class="space-y-6 min-w-[32rem]">
        @if(!$paymentSuccess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="mb-2">Paiement sécurisé</flux:heading>
                    <flux:subheading>
                        Achat du fichier CAO - Chat #{{ $chatId }}
                    </flux:subheading>
                </div>

                @if($screenshotUrl)
                    <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700">
                        <img src="{{ $screenshotUrl }}" alt="Aperçu du fichier CAO" class="w-full h-48 object-cover">
                    </div>
                @endif

                @if($amount)
                    <div class="bg-violet-50 dark:bg-violet-950 border border-violet-200 dark:border-violet-800 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-700 dark:text-zinc-300">Montant à payer</span>
                            <span class="text-2xl font-bold text-violet-600">
                                {{ number_format($amount / 100, 2) }}€
                            </span>
                        </div>
                    </div>
                @endif

                @if($clientSecret)
                    <div class="space-y-4" 
                         x-data="stripePayment('{{ $clientSecret }}')" 
                         x-init="$nextTick(() => initStripe())">
                        <flux:field>
                            <flux:label>Informations de paiement</flux:label>
                            <div id="card-element" class="p-3 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-900">
                            </div>
                            <flux:error name="card" />
                        </flux:field>

                        @if($errorMessage)
                            <flux:callout icon="exclamation-circle" variant="danger">
                                <flux:callout.text>{{ $errorMessage }}</flux:callout.text>
                            </flux:callout>
                        @endif

                        <div class="flex gap-3 justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:button
                                wire:click="closeModal"
                                variant="ghost"
                                x-bind:disabled="processing">
                                Annuler
                            </flux:button>
                            <flux:button
                                @click="handlePayment()"
                                variant="primary"
                                class="!bg-violet-600 hover:!bg-violet-700"
                                x-bind:disabled="processing">
                                <span x-show="!processing">
                                    Payer {{ $amount ? number_format($amount / 100, 2) : '' }}€
                                </span>
                                <span x-show="processing" class="flex items-center gap-2">
                                    <flux:icon.arrow-path class="size-4 animate-spin" />
                                    Traitement...
                                </span>
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-8">
                <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mb-4">
                    <flux:icon.check class="size-8 text-green-600" />
                </div>
                <flux:heading size="lg" class="mb-2">Paiement réussi !</flux:heading>
                <flux:text>Votre fichier CAO est maintenant disponible au téléchargement.</flux:text>
            </div>
        @endif
    </flux:modal>
</div>

<script src="https://js.stripe.com/v3/"></script>

@script
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('stripePayment', (clientSecret) => ({
        stripe: null,
        elements: null,
        cardElement: null,
        processing: false,

        initStripe() {
            if (!clientSecret) {
                console.error('No client secret provided');
                return;
            }

            this.stripe = Stripe('{{ config('cashier.key') }}');
            this.elements = this.stripe.elements();

            this.cardElement = this.elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#424770',
                        '::placeholder': {
                            color: '#aab7c4',
                        },
                    },
                    invalid: {
                        color: '#9e2146',
                    },
                },
            });

            this.cardElement.mount('#card-element');
        },

        async handlePayment() {
            if (this.processing) return;

            this.processing = true;

            try {
                const { error, paymentIntent } = await this.stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card: this.cardElement,
                    }
                });

                if (error) {
                    console.error('Payment error:', error);
                    this.$wire.call('handlePaymentError', error.message);
                } else if (paymentIntent.status === 'succeeded') {
                    console.log('Payment succeeded:', paymentIntent);
                    this.$wire.call('handlePaymentSuccess');
                }
            } catch (err) {
                console.error('Unexpected error:', err);
                this.$wire.call('handlePaymentError', 'Une erreur inattendue s est produite.');
            } finally {
                this.processing = false;
            }
        }
    }));
});
</script>
@endscript
