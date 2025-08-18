{{-- Fenêtre volante du configurateur CAD (draggable) --}}
<aside
    x-data="cadFloatingPanel"
    :style="`transform: translate(${x}px, ${y}px)`"
    class="fixed z-40 w-[360px] max-w-[90vw]
         rounded-2xl border border-violet-500/80 bg-white dark:bg-zinc-900
         ring-1 ring-violet-400/50
         shadow-xl shadow-violet-500/10
         [box-shadow:0_12px_30px_-6px_rgba(124,58,237,0.35),0_6px_18px_-8px_rgba(124,58,237,0.25)]
         scroll-smooth overflow-hidden select-none"
>
    {{-- Header (handle drag) --}}
    <div class="flex items-center justify-between px-4 py-3 bg-violet-50/60 dark:bg-violet-950/20 cursor-move"
         @mousedown="startDrag($event)" @touchstart.passive="startDrag($event)">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-600 text-white text-xs">⚙️</span>
            <h3 class="text-sm font-semibold text-violet-700 dark:text-violet-300">Paramètres de votre pièce</h3>
        </div>

        <flux:button
            variant="ghost"
            @click.stop="open = !open"
            icon="chevron-up"
        />
    </div>

    {{-- Contenu (collapsible) --}}
    <div x-show="open"
         x-transition.opacity
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="p-4 space-y-5 select-text overflow-y-auto h-150"
    >

        <livewire:chat-config :$chat/>

        {{-- Actions viewer --}}
        <section class="grid grid-cols-2 gap-2">
            <button type="button"
                    class="h-9 rounded-xl text-xs bg-violet-600/10 hover:bg-violet-600/20 text-violet-700 dark:text-violet-200"
                    @click="$dispatch('viewer-fit')">
                Recentrer vue
            </button>
            <button type="button"
                    class="h-9 rounded-xl text-xs bg-violet-600/10 hover:bg-violet-600/20 text-violet-700 dark:text-violet-200"
                    @click="$dispatch('viewer-snapshot')">
                Snapshot
            </button>
        </section>

        <flux:heading>Configuration de l'affichage</flux:heading>

        <div class="space-y-6">
            @include('ai-cad::partials.cad-controls')
        </div>

        <div class="flex grow flex-col justify-end items-end">
            <flux:modal.trigger name="buy-file">
                <flux:button variant="filled" color="purple">Acheter la piéce</flux:button>
            </flux:modal.trigger>

            <flux:modal name="buy-file" variant="flyout" class="rounded-l-xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg" level="2" class="font-bold">Acheter le fichier</flux:heading>
                    </div>

                    <div class="p-6 rounded-xl border">
                        <div>

                            <img class="w-64 h-52 p-2.5 mx-auto" src="https://placehold.co/262x201"/>
                        </div>

                        <div class="flex items-center space-x-6 bg-gray-100 p-6 rounded-xl text-sm">
                            <strong>ABCDE - 2345</strong>
                            <div>
                                <strong>Dimension pièce : </strong>
                                <span>100 x 25 x 82 x ep 4 mm</span>
                            </div>
                            <div>
                                <strong>Dimension à plat : </strong>
                                <span>25 x 251 mm</span>
                            </div>
                            <div>
                                <strong>Pliages : </strong>
                                <span>4</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        @hasLimit
                        @php
                            $team = auth()->user()->team;
                            $limit = $team->limits->first();
                            $product = $team->getSubscriptionProduct()
                        @endphp

                        <p>Vous avec un abonement en cours</p>
                        <p>Vous avez consommé {{ $limit->used_amount }}/ {{$product->files_allowed }}</p>
                        @else
                            <p>Vous n'avec pas d'abonement</p>
                        @endif
                    </div>
                </div>
            </flux:modal>
        </div>
        <div class="border-t border-violet-100/60 dark:border-violet-900/40"></div>


    </div>
</aside>

@once
    <script>
        function cadFloatingPanel() {
            return {
                // état panneau
                open: true,

                // position (transform translate)
                x: 0, y: 0,
                startX: 0, startY: 0,   // position souris au début du drag
                baseX: 0, baseY: 0,     // position du panneau au début du drag
                dragging: false,

                // contrôles viewer (mêmes que précédemment)
                edgesShow: true,
                threshold: 45,
                edgeColor: '#000000',
                hoverColor: '#2d6cff',
                selectColor: '#ff3b3b',

                init() {
                    // position par défaut : coin bas-droit avec marge (si pas de state stocké)
                    const saved = JSON.parse(localStorage.getItem('cadPanelPos') || 'null')
                    if (saved && Number.isFinite(saved.x) && Number.isFinite(saved.y)) {
                        this.x = saved.x;
                        this.y = saved.y
                    } else {
                        // positionne par défaut en bas/droite : 24px de marge
                        const w = 360
                        this.x = window.innerWidth - w - 24
                        this.y = window.innerHeight - 320 - 24
                    }

                    // re-contraindre à l’écran si resize
                    window.addEventListener('resize', () => this.clampToViewport())
                },

                // ---- Drag & drop ----
                startDrag(e) {
                    this.dragging = true
                    const isTouch = e.type === 'touchstart'
                    const p = isTouch ? e.touches[0] : e
                    this.startX = p.clientX
                    this.startY = p.clientY
                    this.baseX = this.x
                    this.baseY = this.y

                    const move = (ev) => {
                        if (!this.dragging) return
                        const pp = ev.type.startsWith('touch') ? ev.touches[0] : ev
                        const dx = pp.clientX - this.startX
                        const dy = pp.clientY - this.startY
                        this.x = this.baseX + dx
                        this.y = this.baseY + dy
                        this.clampToViewport()
                    }
                    const end = () => {
                        this.dragging = false
                        window.removeEventListener('mousemove', move)
                        window.removeEventListener('mouseup', end)
                        window.removeEventListener('touchmove', move)
                        window.removeEventListener('touchend', end)
                        // persist
                        localStorage.setItem('cadPanelPos', JSON.stringify({x: this.x, y: this.y}))
                    }

                    window.addEventListener('mousemove', move)
                    window.addEventListener('mouseup', end)
                    window.addEventListener('touchmove', move, {passive: true})
                    window.addEventListener('touchend', end)
                },

                clampToViewport() {
                    // contraintes : 12px de marge
                    const panel = this.$el
                    const rect = panel.getBoundingClientRect()
                    const w = rect.width, h = rect.height
                    const maxX = window.innerWidth - w - 12
                    const maxY = window.innerHeight - h - 12
                    this.x = Math.min(Math.max(this.x, 12), Math.max(maxX, 12))
                    this.y = Math.min(Math.max(this.y, 12), Math.max(maxY, 12))
                },

                // ---- Livewire events (identiques) ----
                emitEdges() {
                    Livewire.dispatch('toggleShowEdges', {show: !!this.edgesShow, threshold: Number(this.threshold)})
                },
                emitEdgeColor() {
                    Livewire.dispatch('updatedEdgeColor', {color: this.edgeColor})
                },
                emitHoverColor() {
                    Livewire.dispatch('updatedHoverColor', {color: this.hoverColor})
                },
                emitSelectColor() {
                    Livewire.dispatch('updatedSelectColor', {color: this.selectColor})
                },
            }
        }
    </script>
@endonce
