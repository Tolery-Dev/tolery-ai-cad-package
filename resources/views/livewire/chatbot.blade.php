<div class="relative h-screen flex flex-col bg-grey-background"
     x-data="{
         isGenerating: false,
         init() {
             // Écouter les événements de génération
             window.addEventListener('cad-generation-started', () => {
                 this.isGenerating = true;
                 aicadLog('[RELOAD PROTECTION] Generation started, protection enabled');
             });

             window.addEventListener('cad-generation-ended', () => {
                 this.isGenerating = false;
                 aicadLog('[RELOAD PROTECTION] Generation ended, protection disabled');
             });

             // Protection contre le reload/fermeture pendant la génération
             window.addEventListener('beforeunload', (e) => {
                 if (this.isGenerating) {
                     aicadLog('[RELOAD PROTECTION] Blocking reload/close attempt');
                     e.preventDefault();
                     e.returnValue = ''; // Chrome nécessite returnValue
                     return ''; // Firefox/Safari
                 }
             });
         }
     }">

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

                    @include('ai-cad::livewire.partials.chat-composer')
                </section>
            </div>
        </div>

        {{-- RIGHT PANEL: Preview/Status Area --}}
        @include('ai-cad::livewire.partials.viewer-panel')
    </div>

    {{-- Stripe Payment Modal Component --}}
    <livewire:stripe-payment-modal />

    {{-- Modal Achat/Abonnement --}}
    @include('ai-cad::livewire.partials.purchase-modal')

    {{-- Polling pour détecter la fin du téléchargement des fichiers CAO en background --}}
    @if ($pendingFilesDownload)
        <div wire:poll.5000ms="checkFilesReady" class="sr-only" aria-hidden="true"></div>
    @endif
</div>

@push('scripts')
<script src="{{ asset('vendor/ai-cad/assets/app.js') }}" defer></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js" defer></script>
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
    const aicadLog   = @js(config('app.debug')) ? console.log.bind(console)   : () => {};
    const aicadWarn  = @js(config('app.debug')) ? console.warn.bind(console)  : () => {};
    const aicadError = @js(config('app.debug')) ? console.error.bind(console) : () => {};

    Alpine.data('cadStreamModal', () => {
        return {
            open: false,
            cancelable: false,
            controller: null,
            overall: 0,
            statusText: 'Initialisation...',
            activeStep: null,
            completedSteps: 0,

            // Retry management
            retryCount: 0,
            maxRetries: 3,
            isRetrying: false,
            retryDelays: [2000, 4000, 8000], // Exponential backoff: 2s, 4s, 8s

            // Error state
            hasError: false,
            errorType: null, // 'network', 'timeout', 'server', 'unknown'
            errorMessage: '',
            teamNotified: false,

            // Store request params for retry
            lastRequest: {
                message: null,
                sessionId: null,
                isEdit: false,
                materialChoice: 'STEEL',
            },

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
                this._onLivewire = ({message, sessionId, isEdit = false, materialChoice = 'STEEL'}) => comp.startStream(message, sessionId, isEdit, materialChoice);
                Livewire.on('aicad:startStream', this._onLivewire);
                Livewire.on('aicad-start-stream', this._onLivewire);

                // Focus sur le textarea du composer Flux
                Livewire.on('tolery-chat-focus-input', () => {
                    const composer = document.querySelector('[data-flux-composer]');
                    const textarea = composer?.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                        // setSelectionRange ne fonctionne que sur input/textarea standard
                        if (typeof textarea.setSelectionRange === 'function') {
                            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                        }
                    }
                });
            },
            reset() {
                this.overall = 0;
                this.statusText = 'Initialisation...';
                this.activeStep = null;
                this.completedSteps = 0;
                this.steps.forEach(s => s.state = 'inactive');
                this.stepMessageIndex = {};
                // Reset error state (but not retry count - that's managed separately)
                this.hasError = false;
                this.errorType = null;
                this.errorMessage = '';
            },
            resetRetryState() {
                this.retryCount = 0;
                this.isRetrying = false;
                this.teamNotified = false;
            },
            classifyError(error) {
                // Classify error type for better user feedback
                if (error.name === 'AbortError') {
                    return {
                        type: 'cancelled',
                        message: 'La génération a été annulée.',
                        canRetry: false,
                    };
                }
                if (error instanceof TypeError || error.message?.includes('fetch') || error.message?.includes('network')) {
                    return {
                        type: 'network',
                        message: 'Problème de connexion réseau. Vérifiez votre connexion internet.',
                        canRetry: true,
                    };
                }
                if (error.message?.includes('timeout') || error.message?.includes('Timeout')) {
                    return {
                        type: 'timeout',
                        message: 'Le serveur met trop de temps à répondre.',
                        canRetry: true,
                    };
                }
                if (error.message?.includes('500') || error.message?.includes('502') || error.message?.includes('503')) {
                    return {
                        type: 'server',
                        message: 'Le serveur rencontre un problème temporaire.',
                        canRetry: true,
                    };
                }
                if (error.message?.includes('401') || error.message?.includes('403')) {
                    return {
                        type: 'auth',
                        message: 'Session expirée. Veuillez rafraîchir la page.',
                        canRetry: false,
                    };
                }
                return {
                    type: 'unknown',
                    message: 'Une erreur inattendue s\'est produite.',
                    canRetry: true,
                };
            },
            getRetryMessage() {
                const remaining = this.maxRetries - this.retryCount;
                const delay = this.retryDelays[this.retryCount] / 1000;
                return `Nouvelle tentative dans ${delay}s... (${remaining} essai${remaining > 1 ? 's' : ''} restant${remaining > 1 ? 's' : ''})`;
            },
            async notifyTeamOfFailure() {
                if (this.teamNotified) return;

                aicadLog('[AICAD] 📧 Notifying Tolery team of repeated failure...');
                try {
                    await $wire.notifyStreamFailure({
                        message: this.lastRequest.message?.substring(0, 500),
                        sessionId: this.lastRequest.sessionId,
                        errorType: this.errorType,
                        errorMessage: this.errorMessage,
                        retryCount: this.retryCount,
                    });
                    this.teamNotified = true;
                    aicadLog('[AICAD] ✅ Team notification sent successfully');
                } catch (e) {
                    aicadError('[AICAD] ❌ Failed to notify team:', e);
                }
            },
            async retryStream() {
                if (!this.lastRequest.message) {
                    aicadError('[AICAD] Cannot retry: no previous request stored');
                    return;
                }

                this.hasError = false;
                this.errorMessage = '';
                this.errorType = null;

                await this.startStream(
                    this.lastRequest.message,
                    this.lastRequest.sessionId,
                    this.lastRequest.isEdit,
                    this.lastRequest.materialChoice ?? 'STEEL'
                );
            },
            manualRetry() {
                // User clicked retry button - reset retry count and try again
                this.retryCount = 0;
                this.teamNotified = false;
                this.retryStream();
            },
            getDetailedMessage(stepKey) {
                const messages = this.stepMessages[stepKey];
                if (!messages || messages.length === 0) return null;

                // Initialiser l'index pour cette étape si nécessaire
                if (this.stepMessageIndex[stepKey] === undefined) {
                    this.stepMessageIndex[stepKey] = 0;
                } else {
                    // Passer au message suivant (avec boucle)
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
                // Utiliser le message détaillé si disponible, sinon fallback sur message API
                const detailedMessage = this.getDetailedMessage(stepKey);
                this.statusText = detailedMessage || message || status || 'Traitement en cours...';
            },
            async startStream(message, sessionId, isEdit = false, materialChoice = 'STEEL') {
                // Check if this is a retry or a new request
                const isRetryAttempt = this.isRetrying;

                // Reset UI state
                this.reset();
                this.open = true;
                window.dispatchEvent(new CustomEvent('cad-generation-started'));
                this.cancelable = true;
                this.controller = new AbortController();

                // Store request params for potential retry (only on new requests)
                if (!isRetryAttempt) {
                    this.resetRetryState();
                    this.lastRequest = { message, sessionId, isEdit, materialChoice };
                }
                this.isRetrying = false;

                const url = @js(route('ai-cad.stream.generate-cad'));

                aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                aicadLog(`[AICAD] 🚀 ${isRetryAttempt ? 'RETRY' : 'NEW'} CAD GENERATION REQUEST`);
                aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                aicadLog('[AICAD] 🔑 Session ID:', sessionId || 'NEW SESSION (no ID provided)');
                aicadLog('[AICAD] 📝 Message:', message?.substring(0, 150) + (message?.length > 150 ? '...' : ''));
                aicadLog('[AICAD] ✏️  Is Edit Request:', isEdit ? 'YES' : 'NO');
                aicadLog('[AICAD] 🔄 Retry Attempt:', isRetryAttempt ? `#${this.retryCount}` : 'N/A (new request)');
                aicadLog('[AICAD] 📍 API Endpoint:', url);
                aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                try {
                    aicadLog('[AICAD] 📡 Sending fetch request...');
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'text/event-stream',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({
                            message: String(message ?? ''),
                            session_id: String(sessionId ?? ''),
                            is_edit_request: isEdit,
                            material_choice: materialChoice ?? 'STEEL',
                        }),
                        signal: this.controller.signal,
                    });

                    aicadLog('[AICAD] 📨 Response received:', {
                        status: res.status,
                        statusText: res.statusText,
                        ok: res.ok,
                        headers: Object.fromEntries(res.headers.entries()),
                        bodyExists: !!res.body,
                    });

                    if (!res.ok || !res.body) {
                        aicadError('[AICAD] ❌ Response not OK or no body:', {
                            status: res.status,
                            statusText: res.statusText,
                            ok: res.ok,
                            bodyExists: !!res.body,
                        });
                        throw new Error(`Stream error: ${res.status} ${res.statusText}`);
                    }
                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const {value, done} = await reader.read();
                        if (done) break;
                        buffer += decoder.decode(value, {stream: true});

                        let sep;
                        while ((sep = buffer.indexOf('\n\n')) !== -1) {
                            const packet = buffer.slice(0, sep);
                            buffer = buffer.slice(sep + 2);

                            const lines = packet.split('\n').map(l => l.trim());
                            for (const line of lines) {
                                if (!line || line.startsWith(':')) continue;
                                if (!line.startsWith('data:')) continue;

                                const json = line.slice(5).trim();
                                if (!json || json === '[DONE]') continue;

                                let payload;
                                try {
                                    payload = JSON.parse(json);
                                } catch {
                                    continue;
                                }

                                if (payload.final_response) {
                                    const resp = payload.final_response || {};

                                    // Extract session_id from various possible locations
                                    const extractedSessionId = resp.session_id || payload.session_id || sessionId;

                                    aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                    aicadLog('[AICAD] ✅ GENERATION COMPLETED - Final Response Received');
                                    aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                    aicadLog('[AICAD] 🔑 Session ID:', extractedSessionId || 'N/A');
                                    aicadLog('[AICAD] 🔍 Full payload keys:', Object.keys(payload));
                                    aicadLog('[AICAD] 🔍 Response keys:', Object.keys(resp));
                                    if (resp.obj_export) {
                                        aicadLog('[AICAD] 📦 OBJ File:', resp.obj_export);
                                    }
                                    if (resp.step_export) {
                                        aicadLog('[AICAD] 📐 STEP File:', resp.step_export);
                                    }
                                    if (resp.tessellated_export) {
                                        aicadLog('[AICAD] 🔺 Tessellated File:', resp.tessellated_export);
                                    }
                                    if (resp.attribute_and_transientid_map) {
                                        aicadLog('[AICAD] 🗺️  Attribute Map:', resp.attribute_and_transientid_map);
                                    }
                                    if (resp.technical_drawing) {
                                        aicadLog('[AICAD] 📄 Technical Drawing:', resp.technical_drawing);
                                    }
                                    if (resp.screenshot) {
                                        aicadLog('[AICAD] 📸 Screenshot:', resp.screenshot);
                                    }
                                    if (resp.manufacturing_errors && resp.manufacturing_errors.length > 0) {
                                        aicadWarn('[AICAD] ⚠️  Manufacturing Errors:', resp.manufacturing_errors);
                                    }
                                    if (resp.chat_response) {
                                        aicadLog('[AICAD] 💬 Chat Response:', resp.chat_response.substring(0, 200) + (resp.chat_response.length > 200 ? '...' : ''));
                                    }
                                    aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                                    // Wait for message to be saved before refreshing
                                    await $wire.saveStreamFinal(resp);
                                    this.markStep('complete', 'Completed', resp.chat_response || 'Completed', 100);

                                    // Force Livewire component refresh to update UI
                                    await $wire.$refresh();

                                    // Success! Reset retry state
                                    this.resetRetryState();

                                    this.cancelable = true;
                                    setTimeout(() => this.close(), 800);
                                    window.dispatchEvent(new CustomEvent('cad-generation-ended'));
                                    continue;
                                }

                                const step = payload.step || null;
                                const status = payload.status || '';
                                const msg = payload.message || '';
                                const pct = typeof payload.overall_percentage === 'number' ? payload.overall_percentage : null;
                                if (step) this.markStep(step, status, msg, pct ?? this.overall);
                            }
                        }
                    }
                } catch (e) {
                    // Detailed error logging
                    aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                    aicadError('[AICAD] ❌ STREAM ERROR');
                    aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                    aicadError('[AICAD] 🔑 Session ID:', sessionId || 'N/A');
                    aicadError('[AICAD] 📝 Message:', message?.substring(0, 150));
                    aicadError('[AICAD] 📍 Endpoint:', url);
                    aicadError('[AICAD] 🔄 Retry Count:', this.retryCount, '/', this.maxRetries);
                    aicadError('[AICAD] ⚠️  Error Type:', e.constructor.name);
                    aicadError('[AICAD] ⚠️  Error Message:', e.message);
                    aicadError('[AICAD] 📍 Stack:', e.stack);
                    aicadError('[AICAD] 🔍 Error Object:', {
                        name: e.name,
                        message: e.message,
                        cause: e.cause,
                        isAbortError: e.name === 'AbortError',
                        isNetworkError: e instanceof TypeError,
                    });
                    aicadLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                    // Classify the error
                    const errorInfo = this.classifyError(e);
                    this.errorType = errorInfo.type;
                    this.errorMessage = errorInfo.message;

                    aicadLog('[AICAD] 🏷️  Error classified as:', errorInfo.type);
                    aicadLog('[AICAD] 💬 User message:', errorInfo.message);
                    aicadLog('[AICAD] 🔁 Can retry:', errorInfo.canRetry);

                    // Handle cancelled requests (user aborted)
                    if (errorInfo.type === 'cancelled') {
                        this.hasError = false;
                        this.statusText = errorInfo.message;
                        this.cancelable = true;
                        window.dispatchEvent(new CustomEvent('cad-generation-ended'));
                        return;
                    }

                    // Check if we can and should retry
                    if (errorInfo.canRetry && this.retryCount < this.maxRetries) {
                        this.retryCount++;
                        const delay = this.retryDelays[this.retryCount - 1] || 8000;

                        aicadLog(`[AICAD] 🔄 Scheduling retry #${this.retryCount} in ${delay}ms...`);

                        this.hasError = false;
                        this.statusText = this.getRetryMessage();
                        this.cancelable = true;

                        // Schedule retry with exponential backoff
                        this.isRetrying = true;
                        setTimeout(() => {
                            if (this.open && this.isRetrying) {
                                aicadLog(`[AICAD] 🔄 Executing retry #${this.retryCount}...`);
                                this.retryStream();
                            }
                        }, delay);
                    } else {
                        // Max retries reached or error not retryable
                        aicadError('[AICAD] ❌ Max retries reached or error not retryable');

                        this.hasError = true;
                        this.cancelable = true;

                        if (this.retryCount >= this.maxRetries) {
                            // Notify team of persistent failure
                            this.statusText = 'La génération a échoué après plusieurs tentatives.';
                            await this.notifyTeamOfFailure();
                        } else {
                            this.statusText = errorInfo.message;
                        }

                        window.dispatchEvent(new CustomEvent('cad-generation-ended'));
                    }
                }
            },
            close() {
                try {
                    this.controller?.abort();
                } catch {}
                this.open = false;
                window.dispatchEvent(new CustomEvent('cad-generation-ended'));
            }
        }
    });

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
