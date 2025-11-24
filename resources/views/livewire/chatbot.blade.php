<div class="relative h-screen flex flex-col bg-grey-background">

    @include('ai-cad::livewire.partials.chat-header')

    {{-- Main Content Area: Chat (left) + Preview (right) --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- LEFT PANEL: Chat Area (narrower: 400px) --}}
        <section class="w-[35%] shrink-0 flex flex-col bg-white dark:bg-zinc-950 rounded-tl-[24px] rounded-bl-[24px] overflow-hidden">
            {{-- Greeting Header --}}
            <div class="px-6 pt-6 pb-4 shrink-0">
                <flux:heading size="xl" class="flex items-center gap-3">
                    <img src="{{ Vite::asset('resources/images/chat-icon.png')}}" alt="" class="w-10 h-10">
                    <span>Bonjour {{ auth()->user()->firstname }} !</span>
                </flux:heading>
            </div>

            {{-- Messages Area (Scrollable) --}}
            <div id="chat-scroll"
                 x-data="{ scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                 x-init="$nextTick(()=>scrollToEnd())"
                 x-on:tolery-chat-append.window="scrollToEnd()"
                 class="flex-1 overflow-y-auto px-6 py-6">

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
                {key: 'analysis', label: 'Analysis', state: 'inactive'},
                {key: 'parameters', label: 'Parameters', state: 'inactive'},
                {key: 'generation_code', label: 'Generation', state: 'inactive'},
                {key: 'export', label: 'Export', state: 'inactive'},
                {key: 'complete', label: 'Complete', state: 'inactive'},
            ],
            init() {
                const comp = this;
                this._onLivewire = ({message, sessionId, isEdit = false}) => comp.startStream(message, sessionId, isEdit);
                Livewire.on('aicad:startStream', this._onLivewire);
                Livewire.on('aicad-start-stream', this._onLivewire);

                // Listen for cached stream event
                this._onCached = ({cachedData, simulationDuration}) => comp.simulateCachedStream(cachedData, simulationDuration);
                Livewire.on('aicad-start-cached-stream', this._onCached);

                const input = document.querySelector('#message') ?? document.querySelector('[wire\\:model=\"message\"]');
                Livewire.on('tolery-chat-focus-input', () => {
                    if (input) {
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    }
                });
            },
            reset() {
                this.overall = 0;
                this.statusText = 'Initializing…';
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
                this.cancelable = true;
                this.controller = new AbortController();

                const url = @js(route('ai-cad.stream.generate-cad'));

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
                                    $wire.saveStreamFinal(resp)
                                    this.markStep('complete', 'Completed', resp.chat_response || 'Completed', 100);
                                    $wire.refreshFromDb();
                                    this.cancelable = true;
                                    setTimeout(() => this.close(), 800);
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
                    console.error(e);
                    this.statusText = 'Stream connection error. Retrying soon…';
                    this.cancelable = true;
                }
            },
            close() {
                try {
                    this.controller?.abort();
                } catch {}
                this.open = false;
            }
        }
    });


</script>
@endscript
