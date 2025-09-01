{{-- Fenêtre volante du configurateur CAD (draggable) --}}
<aside
    x-data="cadFloatingPanel()"
    :style="`transform: translate(${x}px, ${y}px)`"
    @dblclick.stop="open = !open"
    class="fixed z-40 w-[360px] max-w-[90vw]
         rounded-2xl border border-violet-500/80 bg-white dark:bg-zinc-900
         ring-1 ring-violet-400/50
         shadow-xl shadow-violet-500/10
         scroll-smooth overflow-hidden select-none"
    :class="open ? '[box-shadow:0_12px_30px_-6px_rgba(124,58,237,0.35),0_6px_18px_-8px_rgba(124,58,237,0.25)]' : ''"
>
    {{-- Header (handle drag) --}}
    <div class="flex items-center justify-between px-4 py-3 bg-violet-50/60 dark:bg-violet-950/20 cursor-move"
         @mousedown.self="startDrag($event)" @touchstart.self.passive="startDrag($event)">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-600 text-white text-xs">⚙️</span>
            <h3 class="text-sm font-semibold text-violet-700 dark:text-violet-300">Paramètres de votre pièce</h3>
        </div>

        <button
            type="button"
            @click.stop="open = !open"
            @mousedown.stop
            @touchstart.stop
            :aria-expanded="open.toString()"
            :title="open ? 'Réduire' : 'Déployer'"
            class="cursor-pointer inline-flex items-center justify-center h-8 w-8 rounded-lg text-violet-700 hover:bg-violet-600/10 focus:outline-none focus:ring-2 focus:ring-violet-400/60"
        >
            <!-- Chevron qui pivote -->
            <svg xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor"
                 class="h-5 w-5 transition-transform duration-200"
                 :class="open ? 'rotate-180' : ''">
                <path fill-rule="evenodd"
                      d="M12 8.47a.75.75 0 0 1 .53.22l5 5a.75.75 0 1 1-1.06 1.06L12 10.31l-4.47 4.47a.75.75 0 0 1-1.06-1.06l5-5a.75.75 0 0 1 .53-.22z"
                      clip-rule="evenodd"/>
            </svg>
        </button>
    </div>

    {{-- Contenu (collapsible) --}}
    <div
        id="cad-config-panel"
        :aria-hidden="(!open).toString()"
        class="will-change-[max-height,opacity,transform] overflow-hidden transition-[max-height,opacity,transform] duration-300 ease-[cubic-bezier(.22,1,.36,1)] transition-delay-75"
        :class="open ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-1'"
        x-bind:style="open ? 'max-height: 600px' : 'max-height: 0px'"
    >
        <div class="p-4 space-y-5 select-text overflow-y-auto h-150">

            {{-- Nom de la pièce --}}
            <div class="space-y-2">
                <flux:heading size="sm" level="3" class="!mb-0">Nom de la pièce</flux:heading>
                <flux:input type="text" x-model="partName" placeholder="Ex : Plat 200x50 (S235)"
                            @change="emitPartName()"/>
                <div class="text-xs text-gray-500">Utilisé pour vos notes / devis.</div>
            </div>

            {{-- Actions viewer --}}
            <section class="grid grid-cols-3 gap-2">
                <button type="button"
                        class="h-9 rounded-xl text-xs bg-violet-600/10 hover:bg-violet-600/20 text-violet-700 dark:text-violet-200"
                        @click="$dispatch('viewer-fit')">
                    Recentrer
                </button>
                <button type="button"
                        class="h-9 rounded-xl text-xs bg-violet-600/10 hover:bg-violet-600/20 text-violet-700 dark:text-violet-200"
                        @click="toggleMeasure()">
                    <span x-text="measureEnabled ? 'Mesure : activée' : 'Mesure : désactivée'"></span>
                </button>
                <button type="button"
                        class="h-9 rounded-xl text-xs bg-violet-600/10 hover:bg-violet-600/20 text-violet-700 dark:text-violet-200"
                        @click="$dispatch('viewer-repair-normals')">
                    Réparer
                </button>
            </section>

            <section class="space-y-3">
                <div class="text-sm font-medium text-gray-800 dark:text-zinc-100">Choisissez votre matériau</div>
                <div class="grid grid-cols-3 gap-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="mat" x-model="materialPreset" value="acier"
                               @change="applyPreset()" class="h-4 w-4 text-violet-600">
                        <span>Acier</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="mat" x-model="materialPreset" value="aluminium"
                               @change="applyPreset()" class="h-4 w-4 text-violet-600">
                        <span>Aluminum</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="mat" x-model="materialPreset" value="inox"
                               @change="applyPreset()" class="h-4 w-4 text-violet-600">
                        <span>Inox</span>
                    </label>
                </div>
            </section>

            {{-- Détails (modèle) --}}
        <div class="space-y-1">
            <flux:heading size="sm" level="3" class="!mb-0">Modèle</flux:heading>
            <div class="grid grid-cols-2 gap-x-4 text-xs">
                <div class="text-gray-500">Dimensions</div>
                <div class="text-right">
                    <span x-text="model.sizeX"></span> ×
                    <span x-text="model.sizeY"></span> ×
                    <span x-text="model.sizeZ"></span>
                    <span x-text="model.unit"></span>
                </div>
                <div class="text-gray-500">Triangles</div>
                <div class="text-right" x-text="model.triangles ?? '—'"></div>
            </div>
        </div>

        {{-- Détails de la face cliquée --}}
        <div class="space-y-1">
            <flux:heading size="sm" level="3" class="!mb-0">Face sélectionnée</flux:heading>
            <template x-if="face">
                <div class="grid grid-cols-2 gap-x-4 text-xs">
                    <div class="text-gray-500">ID</div>
                    <div class="text-right" x-text="face.realFaceId ?? face.id"></div>

                    <div class="text-gray-500">Centre</div>
                    <div class="text-right">
                        <span x-text="face.centroid?.x ?? '—'"></span>,
                        <span x-text="face.centroid?.y ?? '—'"></span>,
                        <span x-text="face.centroid?.z ?? '—'"></span>
                        <span x-text="face.unit ?? model.unit"></span>
                    </div>

                    <div class="text-gray-500">Surface (approx.)</div>
                    <div class="text-right" x-text="face.area ? `${face.area} ${face.unit}²` : '—'"></div>

                    <div class="text-gray-500">Triangles</div>
                    <div class="text-right" x-text="face.triangles ?? '—'"></div>
                </div>
            </template>
            <template x-if="!face">
                <div class="text-xs text-gray-500">Cliquez sur une face pour afficher ses informations.</div>
            </template>
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
    </div>
</aside>

@once
    <script>
        function cadFloatingPanel() {
            const PRESETS = {
                steel:     { color: '#9ea3a8', metalness: 0.20, roughness: 0.45 },
                aluminum:  { color: '#bfc5ce', metalness: 0.60, roughness: 0.30 },
                stainless: { color: '#d5d8dc', metalness: 0.35, roughness: 0.25 },
            }
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
                materialColor: '#9ea3a8',

                // stats modèle + sélection
                modelStats: { vertices: 0, triangles: 0, sizeX: 0, sizeY: 0, sizeZ: 0, unit: 'mm' },
                selectedFace: null,

                // data shown
                model: { sizeX: '—', sizeY: '—', sizeZ: '—', unit: 'mm', triangles: null },
                face:  null,

                measureEnabled: false,
                materialPreset: 'acier',

                // actions
                emitPartName() {
                    // si tu veux persister : Livewire.dispatch('updatedPartName', { name: this.partName })
                },
                applyPreset() {
                    const p = PRESETS[this.material] || PRESETS.steel
                    this.materialColor = p.color
                    Livewire.dispatch('updatedMaterialPreset', p)
                },

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

                    // stats modèle (émis par ton viewer)
                    window.addEventListener('cad-model-stats', (e) => {
                        const d = e.detail || {}
                        this.model.sizeX = (d.sizeX ?? '—').toFixed ? d.sizeX.toFixed(2) : d.sizeX
                        this.model.sizeY = (d.sizeY ?? '—').toFixed ? d.sizeY.toFixed(2) : d.sizeY
                        this.model.sizeZ = (d.sizeZ ?? '—').toFixed ? d.sizeZ.toFixed(2) : d.sizeZ
                        this.model.unit   = d.unit ?? 'mm'
                        this.model.triangles = d.triangles ?? null
                    })

                    // sélection face (émis par ton viewer)
                    window.addEventListener('cad-selection', (e) => {
                        this.face = e.detail || null
                        // tu peux aussi envoyer la face sélectionnée vers le chat si besoin
                        // Livewire.dispatch('chatObjectClickReal', { objectId: this.face?.realFaceId ?? this.face?.id ?? null })
                    })

                    // applique le preset par défaut au mount
                    this.applyPreset()
                    // this.$nextTick(() => Livewire.dispatch('updatedMaterialColor', {color: this.materialColor}))
                    // this.applyPreset()
                    //
                    // // re-contraindre à l’écran si resize
                    // window.addEventListener('resize', () => this.clampToViewport())
                    //
                    // // écoute les stats du modèle et la sélection envoyées par app.js
                    // window.addEventListener('cad-model-stats', (e) => {
                    //     if (e?.detail) this.modelStats = e.detail
                    // })
                    // window.addEventListener('cad-selection', (e) => {
                    //     this.selectedFace = e?.detail ?? null
                    // })
                    //
                    // // si des stats sont déjà disponibles (modèle chargé avant panneau)
                    // if (window.cadLastStats) this.modelStats = window.cadLastStats
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
                    render()
                },
                recenter() {
                    // côté viewer: écoute window "viewer-fit"
                    window.dispatchEvent(new CustomEvent('viewer-fit'))
                },
                toggleMeasure() {
                    this.measureEnabled = !this.measureEnabled
                    Livewire.dispatch('toggleMeasureMode', { enabled: this.measureEnabled })
                },
                resetMeasure() {
                    Livewire.dispatch('resetMeasure')
                },
                // format helper for sizes
                formatVal(v) {
                    if (v === undefined || v === null || isNaN(v)) return '-'
                    const n = Number(v)
                    return `${n.toFixed(2)} ${this.modelStats.unit}`
                },

            }
        }
    </script>
@endonce
