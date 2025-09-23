{{-- Fenêtre volante du configurateur CAD (draggable) --}}
<aside
    x-data="cadConfigPanel()"
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
                <flux:input
                    type="text"
                    wire:model.live="partName"
                    placeholder="Ex : Plat 200x50 (S235)"/>
                <div class="text-xs text-gray-500">Utilisé pour vos notes / devis.</div>
            </div>

            {{-- Détails / Dimensions globales --}}
            <div class="rounded-xl bg-violet-50/60 border border-violet-100 p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-700">Détails</div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">longueur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeX)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">largeur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeY)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">épaisseur</span>
                        <span class="font-semibold" x-text="fmtThickness()"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">hauteur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeZ)"></span>
                    </div>
                </div>
            </div>

            {{-- Afficher les contours --}}
            <div class="flex items-center justify-between">
                <div class="text-base font-medium text-gray-900">Afficher les contours</div>
                <flux:switch x-model="showEdges" @change="dispatchEdges()"/>
            </div>

            <flux:separator/>

            {{-- Configuration / Matière --}}
            <div class="space-y-2">
                <div class="text-lg font-semibold">Configuration</div>
                <flux:field label="Type de matière">
                    <flux:radio.group x-model="material" @change="setMaterial(material)" variant="segmented" size="sm">
                        <flux:radio value="inox">Inox</flux:radio>
                        <flux:radio value="aluminium">Aluminium</flux:radio>
                        <flux:radio value="acier">Acier</flux:radio>
                    </flux:radio.group>
                </flux:field>
            </div>

            <flux:callout icon="information-circle" size="sm" color="violet" class="text-violet-900">
                    <flux:callout.text>Sélectionnez une face sur le modèle 3D pour plus d’options.</flux:callout.text>
            </flux:callout>

            {{-- Outil de mesure --}}
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-base font-medium text-gray-900">Outil de mesure</div>
                    <div class="text-xs text-gray-500">Cliquez deux points pour afficher la distance</div>
                </div>
                <flux:button
                             @click="toggleMeasure()"
                             size="sm">
                    <span x-text="measureEnabled ? 'Désactiver' : 'Activer'"></span>
                </flux:button>
            </div>

            {{-- Barre d’actions rapide --}}
            <div class="flex items-center justify-between -mt-1">
              <div class="text-sm text-gray-500"></div>
              <flux:button variant="outline" size="sm" icon="arrows-pointing-in" @click="recenter()">
                Recentrer vue
              </flux:button>
            </div>
            <flux:separator/>

            {{-- Infos de sélection --}}
            <div class="space-y-2">
                <div class="text-base font-semibold text-gray-900">Sélection</div>
                <template x-if="selection">
                    <div class="text-sm space-y-1">
                        <div>
                            <span class="text-gray-500">Face ID</span> :
                            <span class="font-medium" x-text="selection.realFaceId || selection.id"></span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div><span class="text-gray-500">L</span> <span class="font-medium"
                                                                            x-text="fmt(selection.bbox?.x)"></span>
                            </div>
                            <div><span class="text-gray-500">l</span> <span class="font-medium"
                                                                            x-text="fmt(selection.bbox?.y)"></span>
                            </div>
                            <div><span class="text-gray-500">h</span> <span class="font-medium"
                                                                            x-text="fmt(selection.bbox?.z)"></span>
                            </div>
                        </div>
                        <div><span class="text-gray-500">Aire</span> : <span class="font-medium"
                                                                             x-text="fmtArea(selection.area)"></span>
                        </div>
                        <div class="text-xs text-gray-500">Centroïde : <span x-text="coord(selection.centroid)"></span>
                        </div>
                    </div>
                </template>
                <template x-if="!selection">
                    <div class="text-sm text-gray-500">Aucune face sélectionnée.</div>
                </template>
            </div>

            <div class="border-t border-violet-100/60 dark:border-violet-900/40"></div>
        </div>
    </div>
</aside>

@once
    <script>
        function cadConfigPanel() {
            return {
                // UI
                showDetails: true,
                measureEnabled: false,

                // State alimenté par app.js (events window)
                stats: {sizeX: 0, sizeY: 0, sizeZ: 0, unit: 'mm'},
                selection: null,
                // position (transform translate)
                x: 0, y: 0,
                startX: 0, startY: 0,   // position souris au début du drag
                baseX: 0, baseY: 0,     // position du panneau au début du drag
                dragging: false,

                // Data
                partName: 'Pièce 001',
                showEdges: false,
                material: 'inox', // inox | aluminium | acier

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
                    // Dimensions globales
                    window.addEventListener('cad-model-stats', ({detail}) => {
                        if (detail) this.stats = detail
                    })
                    // Sélection
                    window.addEventListener('cad-selection', ({detail}) => {
                        this.selection = detail
                    })
                    // Matériau initial
                    this.setMaterial(this.material)
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
                // Helpers d’affichage
                fmt(v) {
                    return (v == null) ? '—' : `${(+v).toFixed(0)} mm`
                },
                fmtArea(v) {
                    return (v == null) ? '—' : `${(+v).toFixed(0)} mm²`
                },
                fmtThickness() {
                    return '—'
                }, // branche quand tu auras l’info
                coord(c) {
                    if (!c) return '—';
                    const u = this.stats.unit || 'mm';
                    return `(${(c.x || 0).toFixed(1)}, ${(c.y || 0).toFixed(1)}, ${(c.z || 0).toFixed(1)}) ${u}`
                },

                // Features
                dispatchEdges() {
                    Livewire.dispatch('toggleShowEdges', {show: this.showEdges, threshold: 45, color: '#000000'})
                },
                setMaterial(name) {
                    this.material = name
                    Livewire.dispatch('updatedMaterialPreset', {preset: name})
                },
                toggleMeasure() {
                    this.measureEnabled = !this.measureEnabled
                    Livewire.dispatch('toggleMeasureMode', {enabled: this.measureEnabled})
                    if (!this.measureEnabled) Livewire.dispatch('resetMeasure')
                },
                recenter() {
                  // Demande au viewer de se recentrer
                  window.dispatchEvent(new CustomEvent('viewer-fit'));
                },
            }
        }
    </script>
@endonce
