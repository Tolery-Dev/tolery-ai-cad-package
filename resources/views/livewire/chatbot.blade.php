<div class="relative h-screen flex flex-col bg-grey-background"
     x-data="{
         isGenerating: false,
         init() {
             // Écouter les événements de génération
             window.addEventListener('cad-generation-started', () => {
                 this.isGenerating = true;
                 console.log('[RELOAD PROTECTION] Generation started, protection enabled');
             });

             window.addEventListener('cad-generation-ended', () => {
                 this.isGenerating = false;
                 console.log('[RELOAD PROTECTION] Generation ended, protection disabled');
             });

             // Protection contre le reload/fermeture pendant la génération
             window.addEventListener('beforeunload', (e) => {
                 if (this.isGenerating) {
                     console.log('[RELOAD PROTECTION] Blocking reload/close attempt');
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
        {{-- LEFT PANEL: Chat Area (narrower: 400px) --}}
        <section class="w-[35%] shrink-0 flex flex-col bg-grey-background rounded-bl-4xl overflow-hidden">
            {{-- Greeting Header --}}
            <div class="bg-white px-6 pt-6 pb-4 shrink-0"
                 x-data="{ isGenerating: false }"
                 @cad-generation-started.window="isGenerating = true"
                 @cad-generation-ended.window="isGenerating = false">
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

            {{-- Messages Area (Scrollable) --}}
            <div id="chat-scroll"
                 x-data="{
                     scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight },
                     isGenerating: false
                 }"
                 x-init="$nextTick(()=>scrollToEnd())"
                 x-on:tolery-chat-append.window="scrollToEnd()"
                 @cad-generation-started.window="isGenerating = true"
                 @cad-generation-ended.window="isGenerating = false"
                 class="flex-1 overflow-y-auto px-6 py-6 bg-white border-b border-grey-stroke">

                @if(empty($messages))
                    @include('ai-cad::livewire.partials.chat-empty-state')
                @else
                    @include('ai-cad::livewire.partials.chat-messages')
                @endif
            </div>

            @include('ai-cad::livewire.partials.chat-composer')
        </section>

        {{-- RIGHT PANEL: Preview/Status Area --}}
        @include('ai-cad::livewire.partials.viewer-panel')
    </div>

    {{-- Stripe Payment Modal Component --}}
    <livewire:stripe-payment-modal />

    {{-- Modal Achat/Abonnement --}}
    @include('ai-cad::livewire.partials.purchase-modal')
</div>

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
    Alpine.data('cadStreamModal', () => {
        return {
            open: false,
            cancelable: false,
            controller: null,
            overall: 0,
            statusText: 'Initialisation...',
            activeStep: null,
            completedSteps: 0,
            steps: [
                {key: 'analysis', label: 'Analyse', state: 'inactive'},
                {key: 'parameters', label: 'Paramètres', state: 'inactive'},
                {key: 'generation_code', label: 'Génération', state: 'inactive'},
                {key: 'export', label: 'Export', state: 'inactive'},
                {key: 'complete', label: 'Terminé', state: 'inactive'},
            ],
            init() {
                const comp = this;
                this._onLivewire = ({message, sessionId, isEdit = false}) => comp.startStream(message, sessionId, isEdit);
                Livewire.on('aicad:startStream', this._onLivewire);
                Livewire.on('aicad-start-stream', this._onLivewire);

                // Listen for cached stream event
                this._onCached = ({cachedData, simulationDuration}) => comp.simulateCachedStream(cachedData, simulationDuration);
                Livewire.on('aicad-start-cached-stream', this._onCached);

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
                this.statusText = 'Initialisation..';
                this.activeStep = null;
                this.completedSteps = 0;
                this.steps.forEach(s => s.state = 'inactive');
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
                this.statusText = message || status || 'Processing…';
            },
            async simulateCachedStream(cachedData, duration = 10000) {
                // Simulate the 5-step stream with cached data
                this.reset();
                this.open = true;
                window.dispatchEvent(new CustomEvent('cad-generation-started'));
                this.cancelable = false; // Don't allow cancel during simulation

                const stepDuration = duration / 5; // 2 seconds per step at 10s total
                const simulatedSteps = cachedData.simulated_steps || {};

                // Step 1: Analysis
                await this.animateStep('analysis', simulatedSteps.analysis || ['Analyse en cours...'], stepDuration, 20);

                // Step 2: Parameters
                await this.animateStep('parameters', simulatedSteps.parameters || ['Calcul des paramètres...'], stepDuration, 40);

                // Step 3: Generation
                await this.animateStep('generation_code', simulatedSteps.generation_code || ['Génération du code...'], stepDuration, 60);

                // Step 4: Export
                await this.animateStep('export', simulatedSteps.export || ['Export des fichiers...'], stepDuration, 80);

                // Step 5: Complete
                await this.animateStep('complete', simulatedSteps.complete || ['Finalisation...'], stepDuration / 2, 95);

                // Mark as complete
                this.markStep('complete', 'Completed', cachedData.chat_response || 'Pièce prête !', 100);

                // Save the cached data to backend
                $wire.saveCachedFinal(cachedData);

                // Close modal after brief delay
                this.cancelable = true;
                setTimeout(() => this.close(), 800);
                window.dispatchEvent(new CustomEvent('cad-generation-ended'));
            },
            async animateStep(stepKey, messages, duration, targetPercentage) {
                const messageCount = messages.length;
                const messageDuration = duration / messageCount;

                for (let i = 0; i < messageCount; i++) {
                    const message = messages[i];
                    const progress = targetPercentage - ((messageCount - i - 1) * 5); // Gradual increase

                    this.markStep(stepKey, i === messageCount - 1 ? 'completed' : 'active', message, progress);

                    // Add small random variation for natural feel
                    const jitter = Math.random() * 400 - 200; // ±200ms
                    await new Promise(resolve => setTimeout(resolve, messageDuration + jitter));
                }
            },
            async startStream(message, sessionId, isEdit = false) {
                this.reset();
                this.open = true;
                window.dispatchEvent(new CustomEvent('cad-generation-started'));
                this.cancelable = true;
                this.controller = new AbortController();

                const url = @js(route('ai-cad.stream.generate-cad'));

                console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                console.log('[AICAD] 🚀 NEW CAD GENERATION REQUEST');
                console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                console.log('[AICAD] 🔑 Session ID:', sessionId || 'NEW SESSION (no ID provided)');
                console.log('[AICAD] 📝 Message:', message?.substring(0, 150) + (message?.length > 150 ? '...' : ''));
                console.log('[AICAD] ✏️  Is Edit Request:', isEdit ? 'YES' : 'NO');
                console.log('[AICAD] 📍 API Endpoint:', url);
                console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                try {
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
                        }),
                        signal: this.controller.signal,
                    });
                    if (!res.ok || !res.body) {
                        throw new Error(`Stream error: ${res.status}`);
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

                                    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                    console.log('[AICAD] ✅ GENERATION COMPLETED - Final Response Received');
                                    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                                    console.log('[AICAD] 🔑 Session ID:', extractedSessionId || 'N/A');
                                    console.log('[AICAD] 🔍 Full payload keys:', Object.keys(payload));
                                    console.log('[AICAD] 🔍 Response keys:', Object.keys(resp));
                                    if (resp.obj_export) {
                                        console.log('[AICAD] 📦 OBJ File:', resp.obj_export);
                                    }
                                    if (resp.step_export) {
                                        console.log('[AICAD] 📐 STEP File:', resp.step_export);
                                    }
                                    if (resp.tessellated_export) {
                                        console.log('[AICAD] 🔺 Tessellated File:', resp.tessellated_export);
                                    }
                                    if (resp.attribute_and_transientid_map) {
                                        console.log('[AICAD] 🗺️  Attribute Map:', resp.attribute_and_transientid_map);
                                    }
                                    if (resp.technical_drawing) {
                                        console.log('[AICAD] 📄 Technical Drawing:', resp.technical_drawing);
                                    }
                                    if (resp.screenshot) {
                                        console.log('[AICAD] 📸 Screenshot:', resp.screenshot);
                                    }
                                    if (resp.manufacturing_errors && resp.manufacturing_errors.length > 0) {
                                        console.warn('[AICAD] ⚠️  Manufacturing Errors:', resp.manufacturing_errors);
                                    }
                                    if (resp.chat_response) {
                                        console.log('[AICAD] 💬 Chat Response:', resp.chat_response.substring(0, 200) + (resp.chat_response.length > 200 ? '...' : ''));
                                    }
                                    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                                    // Wait for message to be saved before refreshing
                                    await $wire.saveStreamFinal(resp);
                                    this.markStep('complete', 'Completed', resp.chat_response || 'Completed', 100);

                                    // Force Livewire component refresh to update UI
                                    await $wire.$refresh();

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
                    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                    console.error('[AICAD] ❌ STREAM ERROR');
                    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                    console.error('[AICAD] 🔑 Session ID:', sessionId || 'N/A');
                    console.error('[AICAD] ⚠️  Error:', e.message);
                    console.error('[AICAD] 📍 Stack:', e.stack);
                    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                    this.statusText = 'Stream connection error. Retrying soon…';
                    this.cancelable = true;
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
