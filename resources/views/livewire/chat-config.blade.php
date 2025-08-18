<form wire:submit="save">
    <div class="space-y-6">
        <flux:input wire:model.live="form.name" label="Nom de la piéce" type="text" />

        <flux:radio.group variant="segmented" wire:model.live="form.materialFamily" label="Choisisser votre matériau">
            @foreach (\Tolery\AiCad\Enum\MaterialFamily::cases() as $material)
                <flux:radio :value="$material->value" :label="$material->label()" />
            @endforeach
        </flux:radio.group>
    </div>
</form>
