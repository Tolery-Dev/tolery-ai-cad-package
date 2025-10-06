@php
// Ajuste ici si ton header fait autre chose que 96px
$HEADER_H = 120; // en px
@endphp

<div class="h-[calc(100vh-{{ $HEADER_H }}px)] px-6 pt-4">

    {{-- Grille pleine hauteur de fenêtre (moins le header) --}}
    <div class="grid grid-cols-[380px_1fr] gap-6 h-full">

        {{-- GAUCHE (1/3) — Chat sticky full height --}}
        <section class="flex flex-col h-full min-h-0">
            <div class="sticky"
                 style="top: 16px; height: calc(100vh - {{ $HEADER_H }}px - 32px);">
                <div
                    class="h-full dark:bg-zinc-900 flex flex-col overflow-hidden">
                    {{-- Header chat --}}
                    <header
                        class="px-4 py-3 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-zinc-100">Tolery • Assistant CAO</h2>
                        @if($isProcessing ?? false)
                        <span class="text-xs text-blue-600">Calcul...</span>
                        @endif
                    </header>

                    {{-- Messages (scroll) --}}
                    <div id="chat-scroll"
                         x-data="{ scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                         x-init="$nextTick(()=>scrollToEnd())"
                         x-on:tolery-chat-append.window="scrollToEnd()"
                         class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        
                        {{-- Prompts prédéfinis (uniquement si aucun message) --}}
                        @if(empty($messages))
                            <div class="space-y-3">
                                <div class="text-sm text-gray-600 dark:text-zinc-400 font-medium mb-3">
                                    Exemples de pièces :
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <button 
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je souhaite un fichier pour une plaque de dimensions 200x100x3mm avec des rayons de 5mm dans chaque coin')"
                                        class="text-left p-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 hover:border-violet-300 transition-all group">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">📄</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Plaque</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">200×100×3mm avec rayons d'angles</div>
                                            </div>
                                        </div>
                                    </button>

                                    <button 
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je veux un fichier pour une platine de 200mm de longueur, 200mm en largeur, épaisseur 5mm. Il faut 4 perçages taraudés M6 dans chaques coins situés à 25mm des bords. Peux tu ajouter des rayons de 15mm dans chaque angle')"
                                        class="text-left p-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 hover:border-violet-300 transition-all group">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">⚙️</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Platine taraudée</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">200×200×5mm, 4 taraudages M6 aux coins</div>
                                            </div>
                                        </div>
                                    </button>

                                    <button 
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Créer un support en forme de L, avec une base de 100 mm, une hauteur de 60 mm, une largeur de 30 mm, d\'épaisseur 2 mm, avec un pli à 90° et un rayon de pliage intérieur de 2 mm et exterieur de 4mm, comprenant deux trous de 6 mm de diamètre sur la base espacés de 70 mm, centrés en largeur, ainsi qu\'un trou de 8 mm de diamètre centré sur la partie de 60mm. Ajouter des rayons de 5mm dans chaque coins')"
                                        class="text-left p-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 hover:border-violet-300 transition-all group">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">📐</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Support en L</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">Base 100mm, hauteur 60mm, pli 90°</div>
                                            </div>
                                        </div>
                                    </button>

                                    <button 
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je souhaite créer un fichier CAO pour un tube rectangulaire de 1400 mm de long, avec une section de 60 x 30 mm, une épaisseur de 2 mm, des coupes droites à chaque extrémité, un rayon intérieur égal à l\'épaisseur (2 mm) et un rayon extérieur égal à deux fois l\'épaisseur (4 mm)')"
                                        class="text-left p-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 hover:border-violet-300 transition-all group">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">🔲</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Tube</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">Rectangulaire 1400mm, section 60×30mm</div>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        @else
                        @forelse ($messages ?? [] as $msg)
                            <article
                                class="flex items-start gap-3 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center
                                {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200' }}">
                                    {{ $msg['role'] === 'user' ? '👤' : '🤖' }}
                                </div>
                                <div class="flex-1 {{ $msg['role'] === 'user' ? 'text-right' : '' }}">
                                    <div class="text-xs text-gray-500 dark:text-zinc-400 mb-1">
                                        {{ $msg['role'] === 'user' ? 'Vous' : 'Tolery' }}
                                        <span class="mx-1">•</span>
                                        <time>{{ \Illuminate\Support\Carbon::parse($msg['created_at'] ??
                                            now())->format('H:i') }}
                                        </time>
                                    </div>
                                    <div
                                        class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50' : 'inline-block bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100' }} rounded-xl px-3 py-2">
                                        {!! nl2br(e($msg['content'] ?? '')) !!}
                                    </div>
                                </div>
                            </article>
                        @empty
                            {{-- Ne devrait jamais arriver ici car les prompts s'affichent quand vide --}}
                        @endforelse
                        @endif
                    </div>

                    {{-- Composer --}}
                    <footer class="border-t border-gray-100 dark:border-zinc-800 p-3">
                        <form wire:submit.prevent="send" class="flex flex-col gap-2">
                            <div>
                                <flux:textarea
                                    id="message"
                                    rows="2"
                                    placeholder="Décrivez votre pièce ou posez une question"
                                    wire:model.defer="message"
                                    x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"
                                    class="rounded-xl transition-all duration-200
                                           border border-violet-500/20 ring-1 ring-violet-500/20
                                           shadow-md shadow-violet-500/10
                                           focus:ring-2 focus:ring-violet-500/50
                                           focus:shadow-lg focus:shadow-violet-500/20
                                           focus:border-violet-500/50"
                                />
                            </div>
                            <div class="flex justify-end">
                                <flux:button type="submit" variant="ghost" icon="paper-airplane"/>
                            </div>
                        </form>
                    </footer>
                </div>
            </div>

            {{-- Modal progression CAD (SSE) --}}
            <div x-data="cadStreamModal()"
                 x-show="open"
                 x-cloak
                 class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-black/40" @click="cancelable ? close() : null"></div>

                <div class="relative w-full max-w-3xl mx-4 overflow-hidden rounded-2xl shadow-2xl">
                    <div
                        class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Processing</h3>
                        <div class="text-sm" x-text="`${completedSteps} out of 5 steps completed`"></div>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 p-6">
                        <div class="grid grid-cols-5 gap-6 mb-6">
                            <template x-for="s in steps" :key="s.key">
                                <div class="flex items-center gap-2">
                                    <span class="h-5 w-5 rounded-full grid place-items-center"
                                          :class="s.state==='done' ? 'bg-violet-600 text-white' : (s.state==='active' ? 'border-2 border-violet-500 text-violet-500' : 'border-2 border-gray-300 text-gray-300')">
                                        <span x-text="s.state === 'done' ? '' : '•'"
                                              :class="s.state !== 'done' ? 'animate-pulse' : ''"></span>
                                    </span>
                                    <span class="text-sm"
                                          :class="s.state === 'inactive' ? 'text-gray-400' : 'text-gray-900 dark:text-zinc-100'"
                                          x-text="s.label">
                                    </span>
                                </div>
                            </template>
                        </div>

                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-2 bg-gradient-to-r from-gray-800 to-violet-600 transition-all duration-300"
                                 :style="`width: ${overall}%`"></div>
                        </div>

                        <div class="mt-4 text-sm text-gray-600 dark:text-zinc-300 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-2 w-2 rounded-full"
                                    :class="activeStep ? 'bg-violet-500' : 'bg-gray-300'"></span>
                                <span x-text="statusText"></span>
                            </div>
                            <div x-text="`${overall}%`"></div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button"
                                    class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
                                    x-show="cancelable" @click="close()">Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- DROITE (2/3) — Viewer plein espace + fond gris + panneau volant --}}
        <section class="relative w-full h-full min-h-0">
            {{-- Fond gris autour du viewer (comme ta maquette) --}}
            <div class="h-full w-full bg-gray-100 p-4">
                <div
                    class="relative h-full rounded-xl border border-gray-200 dark:border-zinc-800 bg-white overflow-hidden shadow-sm">
                    {{-- Le canvas/WebGL prend 100% de la carte blanche --}}
                    <div id="viewer"
                         wire:ignore
                         class="h-full w-full relative rounded-2xl bg-white/70 shadow-inner">
                    </div>
                </div>
            </div>
        </section>

        {{-- Fenêtre volante (drag + toggle, contour/ombre violets) --}}
        @include('ai-cad::partials.cad-config-panel', [
            'stepExportUrl' => $stepExportUrl,
            'objExportUrl' => $objExportUrl,
            'technicalDrawingUrl' => $technicalDrawingUrl
        ])
    </div>
</div>

@script
<script>
    Alpine.data('cadStreamModal', () => {
        return {
            apiBaseUrl: @js(rtrim(config('ai-cad.api.base_url'), '/')),
            open: false,
            cancelable: false,
            controller: null,
            overall: 0,
            statusText: 'Initialisation...',
            activeStep: null,
            completedSteps: 0,
            steps: [
                {key: 'analysis', label: 'Analysis', state: 'inactive'},   // inactive | active | done
                {key: 'parameters', label: 'Parameters', state: 'inactive'},
                {key: 'generation_code', label: 'Generation', state: 'inactive'},
                {key: 'export', label: 'Export', state: 'inactive'},
                {key: 'complete', label: 'Complete', state: 'inactive'},
            ],
            init() {
                const comp = this;
                this._onLivewire = ({
                    message,
                    sessionId,
                    isEdit = false
                }) => comp.startStream(message, sessionId, isEdit);
                Livewire.on('aicad:startStream', this._onLivewire);
                Livewire.on('aicad-start-stream', this._onLivewire);
                const input = document.querySelector('#message'); // adapte l’ID/selector à ton champ
                Livewire.on('tolery-chat-focus-input', () => {
                    if (input) {
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length); // curseur fin de texte
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
                    // marquer toutes les précédentes comme done si nécessaire
                    for (let i = 0; i < idx; i++) {
                        if (this.steps[i].state !== 'done') this.steps[i].state = 'done';
                    }
                    // marquer l'étape courante
                    this.steps[idx].state = status?.toLowerCase().includes('completed') ? 'done' : 'active';
                    this.activeStep = stepKey;
                    this.completedSteps = this.steps.filter(s => s.state === 'done').length;
                }
                if (typeof pct === 'number') {
                    this.overall = Math.max(0, Math.min(100, pct));
                }
                this.statusText = message || status || 'Processing…';
            },
            async startStream(message, sessionId, isEdit = false) {
                this.reset();
                this.open = true;
                this.cancelable = true;
                this.controller = new AbortController();

                // Passage en GET avec paramètres dans l'URL
                const base = this.apiBaseUrl.replace(/\/+$/, '');
                const qs = new URLSearchParams({
                    message: String(message ?? ''),
                    session_id: String(sessionId ?? ''),
                    is_edit_request: isEdit ? 'true' : 'false',
                }).toString();
                const url = `${base}/api/generate-cad-stream?${qs}`;

                try {
                    const res = await fetch(url, {
                        // GET par défaut
                        headers: {
                            'Accept': 'text/event-stream',
                            ...(window.aicadAuthToken ? {'Authorization': `Bearer ${window.aicadAuthToken}`} : {}),
                        },
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

                                    // 1) Envoie le JSON complet au serveur pour persistance
                                    $wire.saveStreamFinal(resp)

                                    // 2) UI: marquer terminé + demander un refresh (recharge pièce/edges)
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
                } catch {
                }
                this.open = false;
            }
        }
    });
</script>
@endscript
