<div class="relative h-screen flex bg-grey-background">

    {{-- Main Content Area: Chat (left) + Preview (right) --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- LEFT PANEL: Chat Area --}}
        <section class="flex-1 flex flex-col bg-white dark:bg-zinc-950 rounded-tl-[24px] rounded-bl-[24px] overflow-hidden">
            {{-- Header with Breadcrumb --}}
            <header class="px-6 pt-8 pb-6 border-b border-grey-stroke dark:border-zinc-800 shrink-0">
                <div class="flex items-center gap-2.5 mb-4">
                    <button class="w-8 h-8 border border-[#CECECE] dark:border-zinc-700 rounded flex items-center justify-center hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2 text-sm font-semibold text-black dark:text-zinc-100">
                        <span>Retour</span>
                        <span class="text-gray-400">/</span>
                        <span>Conversation</span>
                    </div>
                </div>

                <flux:heading size="xl" class="flex items-center gap-3">
                    <img src="{{ Vite::asset('resources/images/tolery-cad-logo.svg')}}" alt="" class="w-10 h-10">
                    <span>Bonjour {{ auth()->user()->firstname }} !</span>
                </flux:heading>
            </header>

            {{-- Messages Area (Scrollable) --}}
            <div id="chat-scroll"
                 x-data="{ scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                 x-init="$nextTick(()=>scrollToEnd())"
                 x-on:tolery-chat-append.window="scrollToEnd()"
                 class="flex-1 overflow-y-auto px-6 py-6">

                @if(empty($messages))
                    {{-- Welcome Card --}}
                    <div class="bg-white dark:bg-zinc-900 border-[0.5px] border-[#B4B4B4] dark:border-zinc-700 rounded-xl p-6 space-y-3 mb-6">
                        <div class="w-6 h-6 flex items-center justify-center">
                            <svg class="w-6 h-6 text-black dark:text-zinc-100" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <flux:text class="text-sm font-medium text-black dark:text-zinc-100">
                            Bienvenue dans notre configurateur intelligent de création de fichier CAO instantanément et sur-mesure pour la tôlerie.
                        </flux:text>
                        <flux:text class="text-sm font-medium text-soft-black dark:text-zinc-400">
                            Vous pouvez démarrer votre demande de création de fichier CAO (STEP) en décrivant le plus précisément votre pièce ci-dessous.
                        </flux:text>
                    </div>
                @else
                    {{-- Messages --}}
                    @forelse ($messages ?? [] as $msg)
                        <article class="flex items-start gap-3 mb-4 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                            <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200' }}">
                                @if($msg['role'] === 'user')
                                    👤
                                @else
                                    <img src="{{ Vite::asset('resources/images/tolery-cad-logo.svg') }}" alt="" class="w-5 h-5">
                                @endif
                            </div>
                            <div class="flex-1 {{ $msg['role'] === 'user' ? 'text-right' : '' }}">
                                <div class="text-xs text-gray-500 dark:text-zinc-400 mb-1">
                                    {{ $msg['role'] === 'user' ? 'Vous' : 'Tolery' }}
                                    <span class="mx-1">•</span>
                                    <time>{{ \Illuminate\Support\Carbon::parse($msg['created_at'] ?? now())->format('H:i') }}</time>
                                </div>
                                <div class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800' : 'inline-block bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100' }} rounded-xl px-3 py-2">
                                    {!! nl2br(e($msg['content'] ?? '')) !!}
                                </div>
                            </div>
                        </article>
                    @empty
                    @endforelse
                @endif
            </div>

            {{-- Bottom Input Section --}}
            <footer class="px-6 py-6 border-t border-grey-stroke dark:border-zinc-800 shrink-0 space-y-4">
                @if(empty($messages))
                    {{-- Suggestions Pills (Figma Design) --}}
                    <div>
                        <p class="text-xs font-medium text-soft-black dark:text-zinc-400 mb-3">
                            Quelques suggestions :
                        </p>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <button
                                wire:click="sendPredefinedPrompt('Je souhaite un fichier pour une plaque de dimensions 200x100x3mm avec des rayons de 5mm dans chaque coin')"
                                class="px-4 py-2 rounded-full bg-fichiers-10 text-fichiers text-xs font-bold hover:opacity-80 cursor-pointer  transition-opacity">
                                📄 Plaque
                            </button>
                            <button
                                wire:click="sendPredefinedPrompt('Je veux un fichier pour une platine de 200mm de longueur, 200mm en largeur, épaisseur 5mm. Il faut 4 perçages taraudés M6 dans chaques coins situés à 25mm des bords. Peux tu ajouter des rayons de 15mm dans chaque angle')"
                                class="px-4 py-2 rounded-full bg-pill-orange-bg text-pill-orange text-xs font-bold hover:opacity-80 cursor-pointer transition-opacity">
                                ⚙️ Platine taraudée
                            </button>
                            <button
                                wire:click="sendPredefinedPrompt('Créer un support en forme de L, avec une base de 100 mm, une hauteur de 60 mm, une largeur de 30 mm, d épaisseur 2 mm, avec un pli à 90° et un rayon de pliage intérieur de 2 mm et exterieur de 4mm, comprenant deux trous de 6 mm de diamètre sur la base espacés de 70 mm, centrés en largeur, ainsi qu un trou de 8 mm de diamètre centré sur la partie de 60mm. Ajouter des rayons de 5mm dans chaque coins')"
                                class="px-4 py-2 rounded-full bg-pill-green-bg text-pill-green text-xs font-bold hover:opacity-80 cursor-pointer  transition-opacity">
                                📐 Support en L
                            </button>
                            <button
                                wire:click="sendPredefinedPrompt('Je souhaite créer un fichier CAO pour un tube rectangulaire de 1400 mm de long, avec une section de 60 x 30 mm, une épaisseur de 2 mm, des coupes droites à chaque extrémité, un rayon intérieur égal à l épaisseur (2 mm) et un rayon extérieur égal à deux fois l épaisseur (4 mm)')"
                                class="px-4 py-2 rounded-full bg-pill-cyan-bg text-pill-cyan text-xs font-bold hover:opacity-80 cursor-pointer transition-opacity">
                                🔲 Tube
                            </button>
                            <button
                                wire:click="sendPredefinedPrompt('Je voudrais une tôle perforée R12 U27,72, en 500 x 1000 mm')"
                                class="px-4 py-2 rounded-full bg-fichiers-10 text-fichiers text-xs font-bold hover:opacity-80 transition-opacity">
                                🔘 Tôle perforée
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Message Input (Figma Design) --}}
                <form wire:submit.prevent="send" class="space-y-3">
                    <div class="bg-grey-background dark:bg-zinc-800 rounded-xl p-4 flex items-center gap-4">
                        <flux:textarea
                            id="message"
                            rows="1"
                            placeholder="Votre message"
                            wire:model.defer="message"
                            x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"
                            class="flex-1 bg-transparent border-none focus:ring-0 text-sm font-medium text-black dark:text-zinc-100 placeholder-soft-black dark:placeholder-zinc-500 p-0 resize-none"
                        />
                        <button type="submit" class="shrink-0 w-6 h-6 text-devis hover:opacity-80 transition-opacity">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>

                    {{-- File Upload Button (Figma Design) - Hidden for now --}}
                    {{-- <button
                        type="button"
                        class="w-[220px] h-14 bg-devis-10 dark:bg-devis/10 border-2 border-dashed border-devis rounded-xl flex items-center justify-center gap-2 hover:bg-opacity-80 transition-all">
                        <svg class="w-6 h-6 text-devis" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H12L14 5H19C19.5304 5 20.0391 5.21071 20.4142 5.58579C20.7893 5.96086 21 6.46957 21 7V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="text-xs font-bold text-devis">
                            Importer fichier
                        </span>
                    </button> --}}
                </form>
            </footer>
        </section>

        {{-- RIGHT PANEL: Preview/Status Area --}}
        <section class="flex-1 bg-grey-background dark:bg-zinc-900 p-8">
            <div class="relative h-full w-full bg-white dark:bg-zinc-950 border border-grey-stroke dark:border-zinc-800 rounded overflow-hidden">
                {{-- Modal progression CAD --}}
                <div x-data="cadStreamModal()"
                     x-show="open"
                     x-cloak
                     class="absolute inset-0 z-50 flex items-start justify-center pt-8 bg-white dark:bg-zinc-950">
                    <div class="w-full max-w-4xl mx-4">
                        <div class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
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

                            <div class="w-full h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
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

                {{-- 3D Viewer --}}
                <div id="viewer"
                     wire:ignore
                     data-screenshot-exists="{{ $screenshotUrl ? 'true' : 'false' }}"
                     class="h-full w-full">
                </div>

                {{-- Config Panel --}}
                @include('ai-cad::partials.cad-config-panel', [
                    'stepExportUrl' => $stepExportUrl,
                    'objExportUrl' => $objExportUrl,
                    'technicalDrawingUrl' => $technicalDrawingUrl,
                    'screenshotUrl' => $screenshotUrl
                ])

                {{-- Empty State --}}
                @if(!$objExportUrl && !$stepExportUrl)
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none p-8">
                        <div class="w-60 h-60 rounded-full bg-gradient-to-br from-violet-100 to-indigo-100 dark:from-violet-900/20 dark:to-indigo-900/20 flex items-center justify-center mb-8">
                            <svg class="w-32 h-32 text-violet-300 dark:text-violet-700" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-black dark:text-zinc-100 text-center max-w-[310px]">
                            Votre pièce apparaîtra ici dès qu'elle sera prête.
                        </p>
                    </div>
                @endif

                {{-- Bouton de téléchargement --}}
                @if($stepExportUrl || $objExportUrl)
                    <div class="absolute bottom-6 left-6 z-10">
                        <flux:button
                            wire:click="initiateDownload"
                            variant="primary"
                            icon="arrow-down-tray"
                            class="!bg-violet-600 hover:!bg-violet-700 !text-white shadow-lg">
                            Télécharger mon fichier CAO
                        </flux:button>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Stripe Payment Modal Component --}}
    <livewire:stripe-payment-modal />

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
                const input = document.querySelector('#message');
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

    document.addEventListener('livewire:init', () => {
        Livewire.on('download-file', (event) => {
            const url = event.url || event[0]?.url;
            if (url) {
                const link = document.createElement('a');
                link.href = url;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                console.log('[AICAD] File download initiated:', url);
            }
        });
    });
</script>
@endscript
