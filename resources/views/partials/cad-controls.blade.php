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
        emitEdges()       { Livewire.dispatch('toggleShowEdges',   { show: this.edgesShow, threshold: Number(this.threshold) }) },
        emitEdgeColor()   { Livewire.dispatch('updatedEdgeColor',  { color: this.edgeColor }) },
        emitHoverColor()  { Livewire.dispatch('updatedHoverColor', { color: this.hoverColor }) },
        emitSelectColor() { Livewire.dispatch('updatedSelectColor',{ color: this.selectColor }) },
    }
}
</script>
@endonce
