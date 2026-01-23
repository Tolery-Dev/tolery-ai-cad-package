<div>
    {{-- Modal de paiement Stripe --}}
    <flux:modal name="stripe-payment" :open="$showModal" wire:model="showModal" class="space-y-6 min-w-[32rem]">
        @if($paymentSuccess)
            {{-- État de succès avec bouton de téléchargement --}}
            <div class="text-center py-8 space-y-6">
                <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <flux:icon.check class="size-8 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg" class="mb-2">Paiement réussi !</flux:heading>
                    <flux:text class="text-zinc-600">
                        Votre fichier CAO est maintenant disponible au téléchargement.
                        <br>
                        <span class="text-sm text-zinc-500">
                            Vous pouvez le télécharger autant de fois que vous le souhaitez dans le menu
                            <span class="font-medium text-violet-600">Fichiers commandés</span>.
                        </span>
                    </flux:text>
                </div>

                <div class="flex flex-col gap-3 pt-4">
                    <flux:button
                        wire:click="downloadAfterPurchase"
                        variant="primary"
                        color="purple"
                        icon="arrow-down-tray"
                        class="w-full">
                        Télécharger les fichiers
                    </flux:button>
                    <flux:button
                        wire:click="closeModal"
                        variant="ghost"
                        class="w-full">
                        Fermer
                    </flux:button>
                </div>
            </div>
        @else
            {{-- Formulaire de paiement --}}
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="mb-2">Paiement sécurisé</flux:heading>
                    <flux:subheading>
                        Achat unique de ce fichier CAO
                    </flux:subheading>
                </div>

                @if($screenshotUrl)
                    <div class="rounded-lg overflow-hidden border border-zinc-200">
                        <img src="{{ $screenshotUrl }}" alt="Aperçu du fichier CAO" class="w-full h-48 object-cover">
                    </div>
                @endif

                @if($amount)
                    <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-700">Montant à payer</span>
                            <span class="text-2xl font-bold text-violet-600">
                                {{ number_format($amount / 100, 2) }}€
                            </span>
                        </div>
                    </div>
                @endif

                @if($clientSecret)
                    <div class="space-y-4"
                         x-data="{
                            stripe: null,
                            elements: null,
                            cardElement: null,
                            cardComplete: false,
                            cardError: null,
                            processing: false,
                            cgvAccepted: false,

                            async init() {
                                // Wait for Stripe to be available
                                await this.waitForStripe();
                                this.initStripe();
                            },

                            waitForStripe() {
                                return new Promise((resolve) => {
                                    if (typeof Stripe !== 'undefined') {
                                        resolve();
                                        return;
                                    }
                                    const check = setInterval(() => {
                                        if (typeof Stripe !== 'undefined') {
                                            clearInterval(check);
                                            resolve();
                                        }
                                    }, 100);
                                });
                            },

                            initStripe() {
                                const stripeKey = '{{ $this->stripeKey }}';
                                if (!stripeKey) {
                                    console.error('No Stripe key provided');
                                    this.cardError = 'Configuration Stripe manquante';
                                    return;
                                }

                                this.stripe = Stripe(stripeKey);
                                this.elements = this.stripe.elements({
                                    clientSecret: '{{ $clientSecret }}',
                                    appearance: {
                                        theme: 'stripe',
                                        variables: {
                                            colorPrimary: '#7B46E4',
                                        }
                                    }
                                });

                                this.cardElement = this.elements.create('payment');
                                this.cardElement.mount('#card-element');

                                this.cardElement.on('change', (event) => {
                                    this.cardComplete = event.complete;
                                    this.cardError = event.error ? event.error.message : null;
                                });
                            },

                            async handlePayment() {
                                if (this.processing) return;

                                this.processing = true;
                                this.cardError = null;

                                try {
                                    const { error, paymentIntent } = await this.stripe.confirmPayment({
                                        elements: this.elements,
                                        confirmParams: {
                                            return_url: window.location.href,
                                        },
                                        redirect: 'if_required'
                                    });

                                    if (error) {
                                        console.error('Payment error:', error);
                                        this.cardError = error.message;
                                        $wire.call('handlePaymentError', error.message);
                                    } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                                        console.log('Payment succeeded:', paymentIntent);
                                        $wire.call('handlePaymentSuccess', paymentIntent.id);
                                    }
                                } catch (err) {
                                    console.error('Unexpected error:', err);
                                    this.cardError = 'Une erreur inattendue s\'est produite.';
                                    $wire.call('handlePaymentError', this.cardError);
                                } finally {
                                    this.processing = false;
                                }
                            }
                         }">
                        <flux:field>
                            <flux:label>Informations de paiement</flux:label>
                            <div id="card-element" class="p-3 border border-zinc-300 rounded-lg bg-white min-h-[60px]">
                                {{-- Stripe Elements will mount here --}}
                            </div>
                        </flux:field>

                        <template x-if="cardError">
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm" x-text="cardError"></div>
                        </template>

                        @if($errorMessage)
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                                {{ $errorMessage }}
                            </div>
                        @endif

                        {{-- CGV Checkbox --}}
                        <div class="flex items-start gap-2 p-3 bg-zinc-50 rounded-lg border border-zinc-200">
                            <input
                                type="checkbox"
                                id="cgv-stripe-checkbox"
                                x-model="cgvAccepted"
                                class="mt-0.5 rounded border-zinc-300 text-violet-600 focus:ring-violet-500">
                            <label for="cgv-stripe-checkbox" class="text-xs text-zinc-600">
                                J'accepte les
                                <a href="{{ route('client.tolerycad.cgv') }}" target="_blank" rel="noopener" class="text-violet-600 hover:text-violet-700 underline">
                                    CGV ToleryCAD
                                </a>
                            </label>
                        </div>

                        <div class="flex gap-3 justify-end pt-4 border-t border-zinc-200">
                            <flux:button
                                wire:click="closeModal"
                                variant="ghost"
                                x-bind:disabled="processing">
                                Annuler
                            </flux:button>
                            <flux:button
                                @click="handlePayment()"
                                variant="primary"
                                color="purple"
                                x-bind:disabled="processing || !cgvAccepted"
                                x-bind:class="{ 'opacity-50 cursor-not-allowed': !cgvAccepted && !processing }">
                                <span x-show="!processing">
                                    Payer {{ $amount ? number_format($amount / 100, 2) : '' }}€
                                </span>
                                <span x-show="processing" class="flex items-center gap-2">
                                    <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Traitement...
                                </span>
                            </flux:button>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-zinc-500">
                        <p>Chargement du formulaire de paiement...</p>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>

{{-- Load Stripe.js outside of Livewire component --}}
<script src="https://js.stripe.com/v3/"></script>

@script
<script>
    // Listen for file download events from this component
    Livewire.on('start-file-download', ({url, filename}) => {
        console.log('[AICAD] Starting download:', url, filename);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
</script>
@endscript
