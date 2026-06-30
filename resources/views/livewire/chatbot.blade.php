<div class="relative h-screen flex flex-col bg-grey-background">

    @include('ai-cad::livewire.partials.chat-header')

    {{-- Main Content Area: Chat (left) + Preview (right) --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- LEFT PANEL: Chat Area --}}
        <div class="w-[35%] shrink-0 flex flex-col bg-grey-background rounded-bl-4xl relative overflow-hidden">

            <div class="flex flex-col h-full">
                {{-- Scrollable Content --}}
                <section id="chat-scroll"
                         class="flex-1 flex flex-col overflow-y-auto"
                         x-data="{
                             scrollToEnd() {
                                 // Ne scroller que si on a des messages
                                 if (@js(count($messages)) > 0) {
                                     this.$el.scrollTop = this.$el.scrollHeight;
                                 }
                             },
                             isGenerating: false
                         }"
                         x-init="$nextTick(()=>scrollToEnd())"
                         x-on:tolery-chat-append.window="scrollToEnd()"
                         @cad-generation-started.window="isGenerating = true"
                         @cad-generation-ended.window="isGenerating = false">

                    @if(empty($messages))
                        {{-- Greeting Header (visible uniquement quand la conversation est vide) --}}
                        <div class="bg-white px-6 pt-3 pb-2">
                            <flux:text size="lg" class="flex items-start gap-2">
                                <img src="{{ asset('vendor/ai-cad/images/bot-icon.svg') }}"
                                     alt="Tolery Bot"
                                     class="h-8 w-8 p-1 bot-avatar"
                                     :class="{ 'bot-thinking': isGenerating }">
                                <span>
                                    Bienvenue dans notre configurateur intelligent de création de fichier CAO (STEP) sur-mesure et instantanément <span class="italic text-violet-600">pour des pièces simples de tôlerie</span>. Vous pouvez démarrer la création de vos fichiers CAO de 3 manières :
                                </span>
                            </flux:text>
                        </div>
                    @endif

                    {{-- Messages Area --}}
                    <div class="flex-1 px-6 pt-3 pb-6 bg-white border-b border-grey-stroke">

                        @if(empty($messages))
                            @include('ai-cad::livewire.partials.chat-empty-state')
                        @else
                            @include('ai-cad::livewire.partials.chat-messages')
                        @endif
                    </div>
                </section>

                @if($hasPendingGeneration)
                    <div class="mx-4 mb-2 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/50 dark:bg-amber-900/20">
                        <svg class="size-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <p class="flex-1 text-sm text-amber-800 dark:text-amber-200">
                            La génération n'a pas pu démarrer suite à un problème de connexion.
                        </p>
                        <button
                            type="button"
                            wire:click="retryPendingGeneration"
                            wire:loading.attr="disabled"
                            class="shrink-0 rounded-md bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600 disabled:opacity-50 transition-colors">
                            <span wire:loading.remove wire:target="retryPendingGeneration">Relancer</span>
                            <span wire:loading wire:target="retryPendingGeneration">...</span>
                        </button>
                    </div>
                @endif

                @include('ai-cad::livewire.partials.chat-composer')
            </div>
        </div>

        {{-- RIGHT PANEL: Preview/Status Area --}}
        @include('ai-cad::livewire.partials.viewer-panel')
    </div>

    {{-- Modal Achat/Abonnement --}}
    @include('ai-cad::livewire.partials.purchase-modal')

    {{-- Modal « Vos fichiers sont en cours de préparation » (#2374) --}}
    @include('ai-cad::livewire.partials.preparing-download-modal')

    {{-- Polling pour détecter la fin du téléchargement des fichiers CAO en background.
         Sert à la fois à rafraîchir les liens d'export et, depuis #2374, à déclencher
         automatiquement un téléchargement différé via la modal de préparation. --}}
    @if ($pendingFilesDownload)
        <div wire:poll.5000ms="checkFilesReady" class="sr-only" aria-hidden="true"></div>
    @endif
</div>

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css" />
@endpush

@push('scripts')
<script src="{{ asset('vendor/ai-cad/assets/app.js') }}" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js" defer></script>
@endpush

@push('styles')
<style>
    @keyframes bot-thinking {
        0%, 100% {
            transform: scale(1) rotate(0deg);
        }
        25% {
            transform: scale(1.1) rotate(-5deg);
        }
        50% {
            transform: scale(1.15) rotate(5deg);
        }
        75% {
            transform: scale(1.1) rotate(-5deg);
        }
    }

    @keyframes bot-pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }

    .bot-avatar {
        transition: all 0.3s ease-in-out;
    }

    .bot-thinking {
        animation: bot-thinking 1.5s ease-in-out infinite, bot-pulse 2s ease-in-out infinite;
        filter: drop-shadow(0 0 8px rgba(123, 70, 228, 0.4));
    }

    /* Typing indicator animation (3 dots) */
    .typing-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 0;
    }

    .typing-indicator span {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #6b7280;
        animation: typing-dot 1.4s ease-in-out infinite;
    }

    .typing-indicator span:nth-child(1) {
        animation-delay: 0s;
    }

    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing-dot {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.7;
        }
        30% {
            transform: translateY(-10px);
            opacity: 1;
        }
    }
</style>
@endpush

@script
<script>
    // Logging conditionnel : actif uniquement en mode debug (APP_DEBUG=true)
    // Évite d'exposer des chemins de fichiers sensibles (OBJ/STEP) en production
    // Défini ici car tous les appels aicadLog sont dans des callbacks (jamais pendant init())
    window.aicadLog   = @js(config('app.debug')) ? console.log.bind(console)   : () => {};
    window.aicadWarn  = @js(config('app.debug')) ? console.warn.bind(console)  : () => {};
    window.aicadError = @js(config('app.debug')) ? console.error.bind(console) : () => {};

    Alpine.data('cadStreamModal', () => {
        return {
            // Phase 2 of issue #152 — the progress modal listens to the Reverb
            // PrivateChannel('chat.{id}') events broadcast by GenerateCadJob.
            // No more browser-held SSE: the job survives reload / tab close.

            open: false,
            overall: 0,
            statusText: 'Initialisation...',
            activeStep: null,
            completedSteps: 0,

            // Estimated remaining generation time (seconds) forwarded by the DFM
            // stream via CadGenerationProgress.estimated_time_seconds (#2475).
            estimatedTimeSeconds: null,

            // Error state — surface a friendly message on CadGenerationFailed
            hasError: false,
            errorMessage: '',

            // Reverb subscription state
            currentChatId: null,
            currentMessageId: null,
            echoChannel: null,

            steps: [
                {key: 'analysis', shortLabel: 'Analyse', label: 'Analyse des informations et dimensions de la pièce', state: 'inactive'},
                {key: 'parameters', shortLabel: 'Paramètres', label: 'Paramètres', state: 'inactive'},
                {key: 'generation_code', shortLabel: 'Génération', label: 'Génération de la pièce et du fichier CAO', state: 'inactive'},
                {key: 'export', shortLabel: 'Export', label: 'Export', state: 'inactive'},
                {key: 'complete', shortLabel: 'Terminé', label: 'Terminé', state: 'inactive'},
            ],
            // Messages detailles par etape pour un meilleur feedback utilisateur
            // Charges dynamiquement depuis la base de donnees via StepMessage
            stepMessages: @js($stepMessages),
            stepMessageIndex: {},
            init() {
                const comp = this;
                this._onSubscribe = ({messageId, chatId}) => comp.subscribeProgress(messageId, chatId);
                Livewire.on('aicad-subscribe-progress', this._onSubscribe);
            },
            reset() {
                this.overall = 0;
                this.statusText = 'Initialisation...';
                this.activeStep = null;
                this.completedSteps = 0;
                this.steps.forEach(s => s.state = 'inactive');
                this.stepMessageIndex = {};
                this.hasError = false;
                this.errorMessage = '';
                this.estimatedTimeSeconds = null;
            },
            // Human-friendly French rendering of estimatedTimeSeconds (e.g. "~3 min 02 s").
            formatEstimatedTime() {
                const total = Number(this.estimatedTimeSeconds);
                if (!Number.isFinite(total) || total <= 0) return '';
                if (total < 60) return `~${Math.round(total)} s`;
                const minutes = Math.floor(total / 60);
                const seconds = Math.round(total % 60);
                return seconds > 0
                    ? `~${minutes} min ${String(seconds).padStart(2, '0')} s`
                    : `~${minutes} min`;
            },
            getDetailedMessage(stepKey) {
                const messages = this.stepMessages[stepKey];
                if (!messages || messages.length === 0) return null;

                if (this.stepMessageIndex[stepKey] === undefined) {
                    this.stepMessageIndex[stepKey] = 0;
                } else {
                    this.stepMessageIndex[stepKey] = (this.stepMessageIndex[stepKey] + 1) % messages.length;
                }

                return messages[this.stepMessageIndex[stepKey]];
            },
            markStep(stepKey, status, message, pct) {
                const idx = this.steps.findIndex(s => s.key === stepKey);
                if (idx >= 0) {
                    for (let i = 0; i < idx; i++) {
                        if (this.steps[i].state !== 'done') this.steps[i].state = 'done';
                    }
                    this.steps[idx].state = status?.toLowerCase().includes('completed') ? 'done' : 'active';
                    this.activeStep = stepKey;
                    this.completedSteps = this.steps.filter(s => s.state === 'done').length;
                }
                if (typeof pct === 'number') {
                    this.overall = Math.max(0, Math.min(100, pct));
                }
                const detailedMessage = this.getDetailedMessage(stepKey);
                this.statusText = detailedMessage || message || status || 'Traitement en cours...';
            },
            /**
             * Subscribe to the chat's broadcast channel for live progress updates.
             * Triggered by the `aicad-subscribe-progress` Livewire event — emitted by
             * Chatbot::send() after dispatching GenerateCadJob, and also by Chatbot::mount()
             * if a generation is still in flight (reload-safe resume).
             */
            subscribeProgress(messageId, chatId) {
                aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                aicadLog(`[AICAD] 🔔 Subscribing to chat.${chatId} (message #${messageId})`);
                aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                this.reset();
                this.open = true;
                this.currentMessageId = messageId;
                this.currentChatId = chatId;
                window.dispatchEvent(new CustomEvent('cad-generation-started'));

                // Defensive cleanup in case a previous subscription is still around
                this.unsubscribe();

                if (typeof window.Echo === 'undefined') {
                    aicadError('[AICAD] window.Echo is not available — broadcasting is misconfigured.');
                    this.hasError = true;
                    this.statusText = 'Connexion temps-réel indisponible.';
                    return;
                }

                this.echoChannel = window.Echo.private(`chat.${chatId}`);

                this.echoChannel
                    .listen('.Tolery\\AiCad\\Events\\CadGenerationStarted', (e) => {
                        aicadLog('[AICAD] 🚀 CadGenerationStarted', e);
                    })
                    .listen('.Tolery\\AiCad\\Events\\CadGenerationProgress', (e) => {
                        if (e.message_id !== this.currentMessageId) return;
                        this.estimatedTimeSeconds = e.estimated_time_seconds || null;
                        this.markStep(e.step, null, e.message, e.pct);
                    })
                    .listen('.Tolery\\AiCad\\Events\\CadGenerationCompleted', (e) => {
                        if (e.message_id !== this.currentMessageId) return;
                        aicadLog('[AICAD] ✅ CadGenerationCompleted', e);
                        this.estimatedTimeSeconds = null;
                        this.markStep('complete', 'Completed', 'Terminé', 100);
                        this.unsubscribe();
                        // Trigger the server-side listener so the new message text is
                        // loaded from DB AND `jsonEdgesLoaded` is redispatched to the
                        // viewer. `$wire.$refresh()` alone only re-renders the
                        // component — it does NOT re-execute mount(), so the typing
                        // indicator stays and the 3D viewer keeps its placeholder.
                        $wire.dispatch('cad-generation-completed', { messageId: e.message_id });
                        setTimeout(() => this.close(), 800);
                        window.dispatchEvent(new CustomEvent('cad-generation-ended'));
                    })
                    .listen('.Tolery\\AiCad\\Events\\CadGenerationFailed', (e) => {
                        if (e.message_id !== this.currentMessageId) return;
                        aicadError('[AICAD] ❌ CadGenerationFailed', e);
                        this.hasError = true;
                        this.statusText = e.error || 'Une erreur est survenue pendant la génération.';
                        this.unsubscribe();
                        $wire.$refresh();
                        window.dispatchEvent(new CustomEvent('cad-generation-ended'));
                    });
            },
            unsubscribe() {
                if (this.echoChannel && this.currentChatId !== null) {
                    try {
                        window.Echo.leave(`chat.${this.currentChatId}`);
                    } catch (e) {
                        aicadError('[AICAD] Failed to leave Echo channel', e);
                    }
                    this.echoChannel = null;
                }
            },
            close() {
                this.unsubscribe();
                this.open = false;
                window.dispatchEvent(new CustomEvent('cad-generation-ended'));
            }
        }
    });

    // Auto-download après retour de Stripe Checkout (ticket #1895).
    // Quand l'URL contient ?auto_download=1, on poll attemptAutoDownload() jusqu'à ce que
    // le webhook Stripe ait synchronisé l'abonnement (max ~9s), puis on déclenche le DL.
    (function autoDownloadAfterSubscription() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('auto_download') !== '1') return;

        // On retire le flag de l'URL pour éviter un re-trigger sur reload
        params.delete('auto_download');
        const cleanQuery = params.toString();
        const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '');
        window.history.replaceState({}, '', cleanUrl);

        const MAX_ATTEMPTS = 6;     // 6 * 1500ms = 9s
        const RETRY_DELAY_MS = 1500;
        let attempts = 0;

        const tryDownload = async () => {
            attempts++;
            aicadLog(`[AICAD] 🔁 Auto-download attempt ${attempts}/${MAX_ATTEMPTS}`);
            try {
                const ready = await $wire.attemptAutoDownload();
                if (ready) {
                    aicadLog('[AICAD] ✅ Auto-download triggered');
                    return;
                }
            } catch (e) {
                aicadError('[AICAD] Auto-download attempt failed:', e);
            }

            if (attempts < MAX_ATTEMPTS) {
                setTimeout(tryDownload, RETRY_DELAY_MS);
            } else {
                aicadWarn('[AICAD] ⚠️ Auto-download gave up after max attempts (subscription webhook delay?)');
            }
        };

        // Petit délai pour laisser Livewire finir son mount initial
        setTimeout(tryDownload, 500);
    })();

    // Listen for chat creation to update URL without redirecting
    Livewire.on('chat-created', ({chatId}) => {
        const newUrl = @js(route('client.tolerycad.show-chatbot', ['chat' => '__CHAT_ID__'])).replace('__CHAT_ID__', chatId);

        aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        aicadLog('[AICAD] 🔗 Updating URL after chat creation');
        aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        aicadLog('[AICAD] 🔑 Chat ID:', chatId);
        aicadLog('[AICAD] 📍 Old URL:', window.location.href);
        aicadLog('[AICAD] 📍 New URL:', newUrl);
        aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Update URL without page reload using HTML5 History API
        window.history.pushState({chatId: chatId}, '', newUrl);
    });

    // Listen for file download events
    Livewire.on('start-file-download', ({url, filename}) => {
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
