<div class="relative h-screen">

    {{-- Grille pleine hauteur : 40% / 60% --}}
    <div class="grid grid-cols-[40%_60%] h-full w-full">

        {{-- GAUCHE (40%) — Chat sur fond gris plein écran --}}
        <section class="flex flex-col h-full bg-white dark:bg-zinc-950 overflow-hidden">
                {{-- Header chat --}}
                <div class="px-8 pt-8 pb-6 shrink-0 border-b border-zinc-100 dark:border-zinc-800">
                    <flux:heading size="xl" class="flex items-center gap-3 mb-4">
                        <img src="{{ Vite::asset('resources/images/tolery-cad-logo.svg')}}" alt="" class="w-10 h-10">
                        <span>Bonjour {{ auth()->user()->firstname }} !</span>
                    </flux:heading>

                    <div class="space-y-3">
                        <flux:text class="text-zinc-700 dark:text-zinc-300">
                            Bienvenue dans notre configurateur intelligent de création de fichier CAO instantanément et sur-mesure pour la tôlerie.
                        </flux:text>
                        <flux:text class="text-zinc-700 dark:text-zinc-300">
                            Vous pouvez démarrer votre demande de création de fichier CAO (STEP) en décrivant le plus précisément votre pièce ci-dessous.
                        </flux:text>
                    </div>
                </div>

                {{-- Bouton de téléchargement --}}
                @if($stepExportUrl)
                    <div class="px-8 py-4 border-b border-zinc-100 dark:border-zinc-800">
                        @if($canDownload)
                            <flux:button
                                wire:click="initiateDownload"
                                variant="primary"
                                icon="arrow-down-tray"
                                class="w-full !bg-violet-600 hover:!bg-violet-700 !text-white">
                                Télécharger mon fichier CAO
                            </flux:button>
                            @if($downloadStatus && isset($downloadStatus['remaining_quota']))
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 text-center block">
                                    {{ $downloadStatus['remaining_quota'] }} téléchargement(s) restant(s) ce mois
                                </flux:text>
                            @endif
                        @else
                            <flux:button
                                wire:click="initiateDownload"
                                variant="primary"
                                icon="lock-closed"
                                class="w-full !bg-violet-600 hover:!bg-violet-700 !text-white">
                                Débloquer le téléchargement
                            </flux:button>
                            @if($downloadStatus && $downloadStatus['reason'] === 'quota_exceeded')
                                <flux:text class="text-xs text-orange-600 dark:text-orange-400 mt-2 text-center block">
                                    Quota mensuel épuisé ({{ $downloadStatus['total_quota'] }}/{{ $downloadStatus['total_quota'] }})
                                </flux:text>
                            @endif
                        @endif
                    </div>
                @endif
                {{-- Messages (scroll) --}}
                <div id="chat-scroll"
                     x-data="{ scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                     x-init="$nextTick(()=>scrollToEnd())"
                     x-on:tolery-chat-append.window="scrollToEnd()"
                     class="flex-1 overflow-y-auto px-8 py-6 space-y-4">

                        {{-- Prompts prédéfinis (uniquement si aucun message) --}}
                        @if(empty($messages))
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-2">
                                    <flux:button
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je souhaite un fichier pour une plaque de dimensions 200x100x3mm avec des rayons de 5mm dans chaque coin')"
                                        variant="outline"
                                        class="!justify-start !h-auto !py-3">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">📄</span>
                                            <div class="flex-1 min-w-0 text-left">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Plaque</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">200×100×3mm avec rayons d'angles</div>
                                            </div>
                                        </div>
                                    </flux:button>

                                    <flux:button
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je veux un fichier pour une platine de 200mm de longueur, 200mm en largeur, épaisseur 5mm. Il faut 4 perçages taraudés M6 dans chaques coins situés à 25mm des bords. Peux tu ajouter des rayons de 15mm dans chaque angle')"
                                        variant="outline"
                                        class="!justify-start !h-auto !py-3">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">⚙️</span>
                                            <div class="flex-1 min-w-0 text-left">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Platine taraudée</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">200×200×5mm, 4 taraudages M6 aux coins</div>
                                            </div>
                                        </div>
                                    </flux:button>

                                    <flux:button
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Créer un support en forme de L, avec une base de 100 mm, une hauteur de 60 mm, une largeur de 30 mm, d épaisseur 2 mm, avec un pli à 90° et un rayon de pliage intérieur de 2 mm et exterieur de 4mm, comprenant deux trous de 6 mm de diamètre sur la base espacés de 70 mm, centrés en largeur, ainsi qu un trou de 8 mm de diamètre centré sur la partie de 60mm. Ajouter des rayons de 5mm dans chaque coins')"
                                        variant="outline"
                                        class="!justify-start !h-auto !py-3">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">📐</span>
                                            <div class="flex-1 min-w-0 text-left">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Support en L</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">Base 100mm, hauteur 60mm, pli 90°</div>
                                            </div>
                                        </div>
                                    </flux:button>

                                    <flux:button
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je souhaite créer un fichier CAO pour un tube rectangulaire de 1400 mm de long, avec une section de 60 x 30 mm, une épaisseur de 2 mm, des coupes droites à chaque extrémité, un rayon intérieur égal à l épaisseur (2 mm) et un rayon extérieur égal à deux fois l épaisseur (4 mm)')"
                                        variant="outline"
                                        class="!justify-start !h-auto !py-3">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">🔲</span>
                                            <div class="flex-1 min-w-0 text-left">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Tube</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">Rectangulaire 1400mm, section 60×30mm</div>
                                            </div>
                                        </div>
                                    </flux:button>

                                    <flux:button
                                        type="button"
                                        wire:click="sendPredefinedPrompt('Je voudrais une tôle perforée R12 U27,72, en 500 x 1000 mm')"
                                        variant="outline"
                                        class="!justify-start !h-auto !py-3">
                                        <div class="flex items-start gap-2">
                                            <span class="text-violet-600 text-lg shrink-0">🔘</span>
                                            <div class="flex-1 min-w-0 text-left">
                                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">Tôle perforée</div>
                                                <div class="text-xs text-gray-600 dark:text-zinc-400 line-clamp-2">R12 U27,72, 500×1000mm</div>
                                            </div>
                                        </div>
                                    </flux:button>
                                </div>
                            </div>
                        @else
                        @forelse ($messages ?? [] as $msg)
                            <article
                                class="flex items-start gap-3 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center
                                {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200' }}">
                                    @if($msg['role'] === 'user')
                                        👤
                                    @else
                                        <img src="{{ Vite::asset('resources/images/tolery-cad-logo.svg')}}" alt="">
                                    @endif
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
                <footer class="w-full border-t border-zinc-100 dark:border-zinc-800 px-8 py-6 shrink-0">
                    <form wire:submit.prevent="send" class="flex flex-col gap-3">
                        <flux:textarea
                            id="message"
                            rows="3"
                            placeholder="Décrivez votre pièce ou posez une question"
                            wire:model.defer="message"
                            x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"
                            class="!w-full resize-none rounded-xl transition-all duration-200
                                   border border-violet-500/20 ring-1 ring-violet-500/20
                                   shadow-md shadow-violet-500/10
                                   focus:ring-2 focus:ring-violet-500/50
                                   focus:shadow-lg focus:shadow-violet-500/20
                                   focus:border-violet-500/50"
                        />
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" icon="paper-airplane">
                                Envoyer
                            </flux:button>
                        </div>
                    </form>
                </footer>
        </section>

        {{-- DROITE (60%) — Viewer sur fond gris (pas de carte blanche) --}}
        <section class="relative h-full bg-zinc-50 dark:bg-zinc-900 px-6 py-4">
            {{-- Modal progression CAD (intégré dans la colonne, pas en overlay) --}}
            <div x-data="cadStreamModal()"
                 x-show="open"
                 x-cloak
                 class="absolute inset-0 z-50 flex items-start justify-center pt-8">
                <div class="w-full max-w-4xl mx-4">
                    <div
                        class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
                        <h3 class="text-lg font-semibold">Processing</h3>
                        <div class="text-sm" x-text="`${completedSteps} out of 5 steps completed`"></div>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 p-6 rounded-b-2xl shadow-2xl">
                        <div class="flex items-center justify-between gap-6 mb-6">
                            <template x-for="s in steps" :key="s.key">
                                <div class="flex flex-col items-center gap-2 flex-1">
                                    <div class="relative">
                                        <span class="h-12 w-12 rounded-full grid place-items-center text-lg font-semibold transition-all duration-300"
                                              :class="s.state==='done' ? 'bg-violet-600 text-white scale-100' : (s.state==='active' ? 'bg-violet-100 text-violet-600 border-2 border-violet-500 animate-pulse scale-110' : 'bg-gray-100 text-gray-400 scale-90')">
                                            <span x-show="s.state === 'done'">✓</span>
                                            <span x-show="s.state !== 'done'"
                                                  class="inline-block"
                                                  :class="s.state === 'active' ? 'animate-spin' : ''"
                                                  x-html="s.state === 'active' ? '●' : '○'"></span>
                                        </span>
                                        {{-- Cercle de progression animé pour l'étape active --}}
                                        <svg x-show="s.state === 'active'" class="absolute inset-0 w-12 h-12 -rotate-90" viewBox="0 0 48 48">
                                            <circle cx="24" cy="24" r="22" fill="none" stroke="#a78bfa" stroke-width="2"
                                                    stroke-dasharray="138" stroke-dashoffset="69"
                                                    class="animate-spin origin-center">
                                            </circle>
                                        </svg>
                                    </div>
                                    <span class="text-xs font-medium text-center"
                                          :class="s.state === 'inactive' ? 'text-gray-400' : 'text-gray-900 dark:text-zinc-100'"
                                          x-text="s.label">
                                    </span>
                                </div>
                            </template>
                        </div>

                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-2 bg-gradient-to-r from-violet-600 to-indigo-600 transition-all duration-500 ease-out"
                                 :style="`width: ${overall}%`"></div>
                        </div>

                        <div class="mt-4 text-sm text-gray-600 dark:text-zinc-300 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-2 w-2 rounded-full animate-pulse"
                                    :class="activeStep ? 'bg-violet-500' : 'bg-gray-300'"></span>
                                <span x-text="statusText"></span>
                            </div>
                            <div class="font-semibold" x-text="`${overall}%`"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Viewer directement sur le fond gris --}}
            <div class="relative h-full w-full rounded-xl overflow-hidden shadow-sm">
                <div id="viewer"
                     wire:ignore
                     class="h-full w-full">
                </div>

                {{-- Fenêtre volante (drag + toggle, contour/ombre violets) --}}
                @include('ai-cad::partials.cad-config-panel', [
                    'stepExportUrl' => $stepExportUrl,
                    'objExportUrl' => $objExportUrl,
                    'technicalDrawingUrl' => $technicalDrawingUrl,
                    'screenshotUrl' => $screenshotUrl
                ])

                {{-- Callout violet quand aucune pièce n'est générée --}}
                @if(!$objExportUrl && !$stepExportUrl)
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <flux:callout icon="sparkles" variant="outline" class="w-full max-w-md bg-violet-50 border-violet-300">
                            <flux:callout.text class="text-black text-center">
                                Commencez votre discussion avec TOLERYCAD, la pièce générée s'affichera ici
                            </flux:callout.text>
                        </flux:callout>
                    </div>
                @endif

                {{-- Bouton de téléchargement (bottom of viewer) --}}
                @if($stepExportUrl || $objExportUrl)
                    <div class="absolute bottom-6 left-6 right-6 z-10">
                        <flux:button
                            wire:click="initiateDownload"
                            size="lg"
                            class="w-full !bg-violet-600 hover:!bg-violet-700 shadow-lg">
                            <flux:icon.arrow-down-tray class="size-5" />
                            Débloquer le téléchargement
                        </flux:button>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Modal Achat/Abonnement --}}
    <flux:modal name="purchase-or-subscribe" :open="$showPurchaseModal" wire:model="showPurchaseModal" class="space-y-6 min-w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="mb-2">Débloquer ce fichier CAO</flux:heading>
                <flux:subheading>
                    @if($downloadStatus && $downloadStatus['reason'] === 'no_subscription')
                        Vous devez être abonné ou acheter ce fichier pour le télécharger.
                    @elseif($downloadStatus && $downloadStatus['reason'] === 'quota_exceeded')
                        Votre quota mensuel est épuisé ({{ $downloadStatus['total_quota'] }}/{{ $downloadStatus['total_quota'] }} fichiers).
                    @else
                        Vous n'avez pas accès à ce téléchargement.
                    @endif
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Option 1: S'abonner --}}
                @if($downloadStatus && isset($downloadStatus['options']['can_subscribe']) && $downloadStatus['options']['can_subscribe'])
                    <flux:card class="border-2 border-violet-200 dark:border-violet-800">
                        <div class="flex flex-col h-full">
                            <div class="flex-1">
                                <flux:heading size="base" class="mb-2 text-violet-600 dark:text-violet-400">
                                    S'abonner
                                </flux:heading>
                                <flux:subheading class="mb-4">
                                    Accès illimité aux téléchargements selon votre plan
                                </flux:subheading>
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Plusieurs fichiers par mois</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Accès prioritaire au support</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Nouvelles fonctionnalités en avant-première</span>
                                    </li>
                                </ul>
                            </div>
                            <flux:button
                                wire:click="redirectToSubscription"
                                variant="primary"
                                class="mt-4 w-full !bg-violet-600 hover:!bg-violet-700">
                                Voir les plans
                            </flux:button>
                        </div>
                    </flux:card>
                @endif

                {{-- Option 2: Achat one-shot --}}
                @if($downloadStatus && isset($downloadStatus['options']['can_purchase']) && $downloadStatus['options']['can_purchase'])
                    <flux:card class="border-2 border-zinc-200 dark:border-zinc-700">
                        <div class="flex flex-col h-full">
                            <div class="flex-1">
                                <flux:heading size="base" class="mb-2">
                                    Acheter ce fichier
                                </flux:heading>
                                <flux:subheading class="mb-4">
                                    Paiement unique pour ce fichier uniquement
                                </flux:subheading>
                                @if(isset($downloadStatus['options']['purchase_price']))
                                    <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">
                                        {{ number_format($downloadStatus['options']['purchase_price'] / 100, 2) }}€
                                    </div>
                                @endif
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Téléchargement immédiat</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Fichier STEP haute qualité</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Accès illimité à ce fichier</span>
                                    </li>
                                </ul>
                            </div>
                            <flux:button
                                wire:click="purchaseFile"
                                variant="outline"
                                class="mt-4 w-full">
                                Acheter maintenant
                            </flux:button>
                        </div>
                    </flux:card>
                @endif
            </div>
        </div>

        <div class="flex gap-2 justify-end pt-6 border-t border-zinc-200 dark:border-zinc-700">
            <flux:modal.close>
                <flux:button variant="ghost">
                    Annuler
                </flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>
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

                // Appel de la route Laravel qui proxifie l'API externe (évite CORS + sécurise le token)
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

{{-- Stripe Payment Modal Component --}}
<livewire:stripe-payment-modal />
