<div x-data="cadControls()" class="flex flex-col gap-4 bg-white max-w-xl">
    <div class="flex items-center justify-between">
        <flux:heading size="sm" level="3" class="!mb-0">Arêtes principales</flux:heading>
        <flux:switch x-model="edgesShow" label="Afficher" @change="emitEdges()" />
    </div>
    <div class="grid grid-cols-5 items-center gap-3">
        <label class="col-span-2 text-sm text-gray-600">Seuil (°)</label>
        <flux:input type="range" min="5" max="120" step="1" x-model.number="threshold"
                    @input.debounce.150ms="emitEdges()" class="col-span-3 w-full" />
        <div class="col-span-5 text-xs text-gray-500">35–60° recommandé.</div>
    </div>
    <div class="grid grid-cols-5 items-center gap-3">
      <label class="col-span-2 text-xs text-gray-600 dark:text-zinc-300">Couleur matière</label>
      <input type="color"
             x-model="materialColor"
             @input.debounce.100ms="Livewire.dispatch('updatedMaterialColor', { color: materialColor })"
             class="col-span-3 h-8 w-16 p-0 bg-transparent rounded-md border border-gray-200 dark:border-zinc-700">
    </div>
    <div class="grid grid-cols-5 items-center gap-3">
        <label class="col-span-2 text-sm text-gray-600">Couleur arêtes</label>
        <flux:input type="color" x-model="edgeColor" @input.debounce.150ms="emitEdgeColor()"
                    class="col-span-3 h-9 w-16 p-0" />
    </div>
    <div class="grid grid-cols-5 items-center gap-3">
        <label class="col-span-2 text-sm text-gray-600">Couleur survol</label>
        <flux:input type="color" x-model="hoverColor" @input.debounce.150ms="emitHoverColor()"
                    class="col-span-3 h-9 w-16 p-0" />
    </div>
    <div class="grid grid-cols-5 items-center gap-3">
        <label class="col-span-2 text-sm text-gray-600">Couleur sélection</label>
        <flux:input type="color" x-model="selectColor" @input.debounce.150ms="emitSelectColor()"
                    class="col-span-3 h-9 w-16 p-0" />
    </div>

    <div class="flex items-center justify-between mt-2">
        <flux:heading size="sm" level="3" class="!mb-0">Mesure</flux:heading>
        <flux:switch x-model="measureEnabled" label="Activer" @change="emitMeasureToggle()" />
    </div>
    <div class="flex items-center gap-3">
        <flux:button size="xs" variant="ghost" @click="resetMeasure()">Réinitialiser</flux:button>
        <span class="text-xs text-gray-500" x-text="measureEnabled ? 'Cliquez deux points sur la pièce' : '—'"></span>
    </div>
</div>

@once
<script>
function cadControls() {
    return {
        edgesShow: true,
        threshold: 45,
        edgeColor: '#000000',
        hoverColor: '#2d6cff',
        selectColor: '#ff3b3b',
        measureEnabled: false,
        emitEdges()       { Livewire.dispatch('toggleShowEdges',   { show: this.edgesShow, threshold: Number(this.threshold) }) },
        emitEdgeColor()   { Livewire.dispatch('updatedEdgeColor',  { color: this.edgeColor }) },
        emitHoverColor()  { Livewire.dispatch('updatedHoverColor', { color: this.hoverColor }) },
        emitSelectColor() { Livewire.dispatch('updatedSelectColor',{ color: this.selectColor }) },
        emitMeasureToggle(){ Livewire.dispatch('toggleMeasureMode', { enabled: this.measureEnabled }) },
        resetMeasure()    { Livewire.dispatch('resetMeasure') },
    }
}
</script>
@endonce
