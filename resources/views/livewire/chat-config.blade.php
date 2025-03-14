<form wire:submit="save">
    <div class="felx space-y-6">

        <flux:input wire:model="form.name" label="Nom de la piéce" type="text" />


        <flux:radio.group wire:model="form.materialFamily" label="Choisisser votre matériau">
            @foreach (\Tolery\AiCad\Enum\MaterialFamily::cases() as $material)
                <flux:radio :value="$material->value" :label="$material->label()" />
            @endforeach
        </flux:radio.group>

        <flux:button>Save</flux:button>
    </div>
</form>
