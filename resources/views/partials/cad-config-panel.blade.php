{{-- Fenêtre volante du configurateur CAD (draggable) --}}
<template x-teleport="body">
    <aside
        x-data="cadConfigPanel({
            initialStepUrl: @js($stepExportUrl ?? null),
            initialObjUrl: @js($objExportUrl ?? null),
            initialScreenshotUrl: @js($screenshotUrl ?? null),
            initialTechnicalDrawingUrl: @js($technicalDrawingUrl ?? null)
        })"
        :style="hasGeneratedInSession ? `position: fixed; top: 0; left: 0; transform: translate(${x}px, ${y}px); z-index: 9999;` : 'display: none;'"
        @dblclick.stop="open = !open"
        class="w-[360px] max-w-[90vw] border border-violet-500/80 ring-1 ring-violet-400/50 shadow-xl shadow-violet-500/10 rounded-2xl bg-white dark:bg-zinc-900 scroll-smooth overflow-hidden select-none"
        :class="open ? '[box-shadow:0_12px_30px_-6px_rgba(124,58,237,0.35),0_6px_18px_-8px_rgba(124,58,237,0.25)]' : ''"
    >
    {{-- Header (handle drag + clickable to toggle) --}}
    <div
        x-show="hasGeneratedInSession"
        @click="open = !open"
        @mousedown="startDrag($event)"
        @touchstart.passive="startDrag($event)"
        class="flex items-center justify-between px-4 py-3 bg-violet-50/60 cursor-pointer hover:bg-violet-100/60 transition-colors"
    >
        <div class="flex items-center gap-2 pointer-events-none">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-600 text-white text-xs">⚙️</span>
            <h3 class="text-sm font-semibold text-violet-700 ">Configurez votre fichier</h3>
        </div>

        <div class="pointer-events-none">
            <!-- Chevron qui pivote -->
            <svg xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor"
                 class="h-5 w-5 text-violet-700 transition-transform duration-200"
                 :class="open ? 'rotate-180' : ''">
                <path fill-rule="evenodd"
                      d="M12 8.47a.75.75 0 0 1 .53.22l5 5a.75.75 0 1 1-1.06 1.06L12 10.31l-4.47 4.47a.75.75 0 0 1-1.06-1.06l5-5a.75.75 0 0 1 .53-.22z"
                      clip-rule="evenodd"/>
            </svg>
        </div>
    </div>

    {{-- Contenu (collapsible) --}}
    <div
        x-show="hasGeneratedInSession"
        id="cad-config-panel"
        :aria-hidden="(!open).toString()"
        class="will-change-[max-height,opacity,transform] overflow-hidden transition-[max-height,opacity,transform] duration-300 ease-[cubic-bezier(.22,1,.36,1)] transition-delay-75"
        :class="open ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-1'"
        x-bind:style="open ? 'max-height: 800px' : 'max-height: 0px'"
    >
        <div class="p-4 space-y-4 select-text overflow-y-auto max-h-[750px]">
            {{-- Instructions (NEW - Priority) --}}
            <flux:callout icon="information-circle" size="sm" color="violet" class="text-violet-900">
                <flux:callout.text>
                    Cliquez sur une face, un perçage, un pliage... l'élément de votre pièce que vous souhaitez pour le modifier directement.
                </flux:callout.text>
            </flux:callout>

            {{-- Sélection (MOVED UP - Priority 1) --}}
            <div class="space-y-2">
                <div class="text-base font-semibold text-gray-900">Sélection</div>
                <template x-if="selection">
                    <div class="text-sm space-y-1 rounded-xl bg-violet-50/60 border border-violet-100 p-3">
                        <div>
                            <span class="text-gray-500">Face ID</span> :
                            <span class="font-medium" x-text="selection.realFaceId || selection.id"></span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div><span class="text-gray-500">L</span> <span class="font-medium" x-text="fmt(selection.bbox?.x)"></span></div>
                            <div><span class="text-gray-500">l</span> <span class="font-medium" x-text="fmt(selection.bbox?.y)"></span></div>
                            <div><span class="text-gray-500">h</span> <span class="font-medium" x-text="fmt(selection.bbox?.z)"></span></div>
                        </div>
                        <div><span class="text-gray-500">Aire</span> : <span class="font-medium" x-text="fmtArea(selection.area)"></span></div>
                        <div class="text-xs text-gray-500">Centroïde : <span x-text="coord(selection.centroid)"></span></div>
                    </div>
                </template>
                <template x-if="!selection">
                    <div class="text-sm text-gray-500 rounded-xl bg-gray-50 border border-gray-200 p-3">
                        Aucune face sélectionnée.
                    </div>
                </template>
            </div>

            <flux:separator/>

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
                        <span class="font-semibold" x-text="fmt(stats.sizeZ)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">hauteur</span>
                        <span class="font-semibold" x-text="'—'"></span>
                    </div>
                </div>
            </div>

            {{-- Afficher les contours --}}
            <div class="flex items-center justify-between">
                <div class="text-base font-medium text-gray-900">Afficher les contours</div>
                <flux:switch x-model="showEdges" @change="dispatchEdges()"/>
            </div>

            <flux:separator/>

            {{-- Outil de mesure --}}
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-base font-medium text-gray-900">Outil de mesure</div>
                    <div class="text-xs text-gray-500">Cliquez deux points pour afficher la distance</div>
                </div>
                <flux:button @click="toggleMeasure()" size="sm">
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

            {{-- Section téléchargements (si fichiers disponibles) --}}
            <div x-show="hasGeneratedInSession" class="space-y-3">
                <div class="text-lg font-semibold text-gray-900">Télécharger les fichiers</div>
                <template x-if="!hasExports()">
                    <div class="rounded-lg border border-amber-200 bg-amber-50/70 text-amber-800 px-4 py-3 text-sm">
                        Abonnez-vous pour récupérer vos créations dès qu'elles sont prêtes.
                    </div>
                </template>
                <div class="grid grid-cols-1 gap-2" x-show="hasExports()">
                    <template x-if="exports.step">
                        <a :href="exports.step"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-between px-4 py-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-violet-600 text-white grid place-items-center text-xs font-semibold">
                                    3D
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Fichier STEP</div>
                                    <div class="text-xs text-gray-500">Format CAO standard</div>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-violet-600 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </a>
                    </template>

                    <template x-if="exports.obj">
                        <a :href="exports.obj"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-between px-4 py-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-indigo-600 text-white grid place-items-center text-xs font-semibold">
                                    OBJ
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Fichier OBJ</div>
                                    <div class="text-xs text-gray-500">Modèle 3D mesh</div>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-indigo-600 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </a>
                    </template>

                    <template x-if="exports.technical_drawing">
                        <a :href="exports.technical_drawing"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-between px-4 py-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-purple-600 text-white grid place-items-center text-xs font-semibold">
                                    PDF
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Plan technique</div>
                                    <div class="text-xs text-gray-500">Mise en plan PDF</div>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-purple-600 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </a>
                    </template>
                </div>

                    <template x-if="exports.screenshot">
                        <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="h-6 w-6 rounded bg-violet-600 text-white grid place-items-center text-xs font-semibold">
                                    📸
                                </div>
                                <div class="text-sm font-medium text-gray-900">Screenshot de la pièce</div>
                            </div>
                            <img :src="exports.screenshot"
                                 alt="Screenshot de la pièce"
                                 class="w-full h-auto rounded-lg border border-violet-200 shadow-sm"
                                 loading="lazy">
                            <a :href="exports.screenshot"
                               download
                               class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                                Télécharger
                            </a>
                        </div>
                    </template>
            </div>

    </div>
    </aside>
</template>

@once
    <script>
        function cadConfigPanel(config = {}) {
            return {
                // UI
                open: false, // État d'ouverture/fermeture du panneau
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

                // Flag pour savoir si une pièce a été générée dans cette session
                hasGeneratedInSession: Boolean(
                    config.initialStepUrl ||
                    config.initialObjUrl ||
                    config.initialTechnicalDrawingUrl ||
                    config.initialScreenshotUrl
                ),

                // Data
                partName: 'Pièce 001',
                showEdges: false,

                // Exports disponibles (initialisés depuis Livewire puis mis à jour par événements)
                exports: {
                    step: config.initialStepUrl || null,
                    obj: config.initialObjUrl || null,
                    technical_drawing: config.initialTechnicalDrawingUrl || null,
                    screenshot: config.initialScreenshotUrl || null,
                },

                init() {
                    // Après teleport, calcule la position initiale adaptée à l'écran
                    this.$nextTick(() => {
                        const saved = JSON.parse(localStorage.getItem('cadPanelPos') || 'null')
                        const panelWidth = 360
                        const viewportWidth = window.innerWidth
                        const viewportHeight = window.innerHeight

                        if (saved && Number.isFinite(saved.x) && Number.isFinite(saved.y)) {
                            // Restaure position sauvegardée
                            this.x = saved.x
                            this.y = saved.y
                        } else {
                            // Position par défaut selon taille d'écran
                            if (viewportWidth < 768) {
                                // Mobile/petit écran : centré en haut
                                this.x = Math.max(12, (viewportWidth - panelWidth) / 2)
                                this.y = 12
                            } else {
                                // Desktop : haut droite
                                this.x = viewportWidth - panelWidth - 24
                                this.y = 24
                            }
                        }

                        // Valide que la position est dans l'écran
                        this.clampToViewport()
                    })

                    // Réajuste sur resize
                    window.addEventListener('resize', () => {
                        this.clampToViewport()
                    })

                    // Dimensions globales
                    // On garde window.addEventListener pour compatibilité avec app.js
                    window.addEventListener('cad-model-stats', ({detail}) => {
                        if (detail) this.stats = detail
                    })
                    // Sélection
                    window.addEventListener('cad-selection', ({detail}) => {
                        this.selection = detail
                    })
                    // Écoute les événements d'export depuis Livewire
                    Livewire.on('cad-exports-updated', ({step, obj, technical_drawing, screenshot}) => {
                        this.exports.step = step || null
                        this.exports.obj = obj || null
                        this.exports.technical_drawing = technical_drawing || null
                        this.exports.screenshot = screenshot || null
                        // Marque qu'une pièce a été générée dans cette session
                        this.hasGeneratedInSession = true
                        // Dispatch browser event for simple panel
                        this.$dispatch('cad-screenshot-updated', { url: screenshot })
                    })
                },
                hasExports() {
                    return this.exports.step || this.exports.obj || this.exports.technical_drawing
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
                toggleMeasure() {
                    this.measureEnabled = !this.measureEnabled
                    Livewire.dispatch('toggleMeasureMode', {enabled: this.measureEnabled})
                    if (!this.measureEnabled) Livewire.dispatch('resetMeasure')
                },
                recenter() {
                  // Demande au viewer de se recentrer
                  this.$dispatch('viewer-fit');
                },
            }
        }
    </script>
@endonce
