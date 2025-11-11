<aside
    x-data="cadSimplePanel()"
    :style="`transform: translate(${x}px, ${y}px)`"
    @dblclick.stop="open = !open"
    class="fixed z-40 w-[360px] max-w-[90vw]
         rounded-2xl border border-violet-500/80 bg-white dark:bg-zinc-900
         ring-1 ring-violet-400/50
         shadow-xl shadow-violet-500/10
         scroll-smooth overflow-hidden select-none"
    :class="open ? '[box-shadow:0_12px_30px_-6px_rgba(124,58,237,0.35),0_6px_18px_-8px_rgba(124,58,237,0.25)]' : ''"
>
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
    <div
        id="cad-config-panel"
        :aria-hidden="(!open).toString()"
        class="flex flex-col gap-5 p-4 bg-white/90 rounded-xl shadow-xl w-full max-w-xl will-change-[max-height,opacity,transform] overflow-hidden transition-[max-height,opacity,transform] duration-300 ease-[cubic-bezier(.22,1,.36,1)] transition-delay-75 "
        :class="open ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-1'"
        x-bind:style="open ? 'max-height: 600px' : 'max-height: 0px'">

        {{-- Nom de la pièce --}}
        <div class="space-y-2">
            <flux:heading size="sm" level="3" class="!mb-0">Nom de la pièce</flux:heading>
            <flux:input type="text" x-model="partName" placeholder="Ex : Plat 200x50 (S235)"
                        @change="emitPartName()"/>
            <div class="text-xs text-gray-500">Utilisé pour vos notes / devis.</div>
        </div>

        {{-- Matériau (presets) --}}
        <div class="space-y-2">
            <flux:heading size="sm" level="3" class="!mb-0">Choisissez votre matériau</flux:heading>
            <div class="grid grid-cols-3 gap-2">
                <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer"
                       :class="material === 'steel' ? 'border-violet-500 ring-2 ring-violet-200' : 'border-gray-200'">
                    <input type="radio" class="sr-only" name="mat" value="steel" x-model="material" @change="applyPreset()" />
                    <span class="h-4 w-4 rounded-full" style="background:#9ea3a8"></span>
                    <span class="text-sm">Acier</span>
                </label>
                <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer"
                       :class="material === 'aluminum' ? 'border-violet-500 ring-2 ring-violet-200' : 'border-gray-200'">
                    <input type="radio" class="sr-only" name="mat" value="aluminum" x-model="material" @change="applyPreset()" />
                    <span class="h-4 w-4 rounded-full" style="background:#bfc5ce"></span>
                    <span class="text-sm">Alu</span>
                </label>
                <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer"
                       :class="material === 'stainless' ? 'border-violet-500 ring-2 ring-violet-200' : 'border-gray-200'">
                    <input type="radio" class="sr-only" name="mat" value="stainless" x-model="material" @change="applyPreset()" />
                    <span class="h-4 w-4 rounded-full" style="background:#d5d8dc"></span>
                    <span class="text-sm">Inox</span>
                </label>
            </div>
            <div class="flex items-center gap-3">
                <label class="text-xs text-gray-600">Couleur matière</label>
                <input type="color" x-model="materialColor"
                       @input.debounce.100ms="emitMaterialColor()"
                       class="h-8 w-16 p-0 bg-transparent rounded-md border border-gray-200">
            </div>
        </div>

        {{-- Actions viewer --}}
        <div class="space-y-2">
            <flux:heading size="sm" level="3" class="!mb-0">Affichage</flux:heading>
            <div class="flex gap-2">
                <flux:button size="xs" variant="primary" @click="recenter()">Recentrer</flux:button>
                <flux:button size="xs" variant="ghost" @click="toggleMeasure()">
                    <span x-text="measureEnabled ? 'Mesure : activée' : 'Mesure : désactivée'"></span>
                </flux:button>
                <flux:button size="xs" variant="ghost" @click="resetMeasure()" >Réinitialiser</flux:button>
            </div>
            <div class="text-xs text-gray-500" x-show="measureEnabled">Cliquez deux points sur la pièce pour mesurer.</div>
        </div>

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
    </div>

        {{-- Screenshot de la pièce --}}
        <div class="space-y-2" x-data="{ screenshotUrl: null }" x-init="
            window.addEventListener('cad-screenshot-updated', (e) => {
                screenshotUrl = e.detail?.url || null;
            });
        ">
            <flux:heading size="sm" level="3" class="!mb-0">Screenshot</flux:heading>
            <template x-if="screenshotUrl">
                <div class="space-y-2">
                    <img :src="screenshotUrl"
                         alt="Screenshot de la pièce"
                         class="w-full h-auto rounded-lg border border-violet-200 shadow-sm"
                         loading="lazy">
                    <flux:button
                        size="xs"
                        variant="primary"
                        @click="window.open(screenshotUrl, '_blank')">
                        Ouvrir en grand
                    </flux:button>
                </div>
            </template>
            <template x-if="!screenshotUrl">
                <div class="text-xs text-gray-500">Aucun screenshot disponible.</div>
            </template>
        </div>
</aside>

@once
<script>
function cadSimplePanel () {
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

        // state
        partName: '',
        material: 'steel',
        materialColor: PRESETS.steel.color,
        measureEnabled: false,

        // data shown
        model: { sizeX: '—', sizeY: '—', sizeZ: '—', unit: 'mm', triangles: null },
        face:  null,

        // actions
        emitPartName() {
            // si tu veux persister : Livewire.dispatch('updatedPartName', { name: this.partName })
        },
        applyPreset() {
            const p = PRESETS[this.material] || PRESETS.steel
            this.materialColor = p.color
            Livewire.dispatch('updatedMaterialPreset', p)
        },
        emitMaterialColor() {
            Livewire.dispatch('updatedMaterialColor', { color: this.materialColor })
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

        // lifecycle
        init () {

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
    }
}
</script>
@endonce
