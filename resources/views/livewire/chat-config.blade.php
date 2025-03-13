<form wire:submit="save">
    <label for="chat-name">Nom de la piéce</label>
    <input id=chat-name" type="text" wire:model="form.name">
    <div>
        @error('form.name') <span class="error">{{ $message }}</span> @enderror
    </div>

    @foreach (\Tolery\AiCad\Enum\MaterialFamily::cases() as $material)
        <input id="{{ $material->value }}" type="radio" wire:model="form.materialFamily" value="{{$material->value}}">
        <label for="{{ $material->value }}">{{ $material->label() }}</label>
    @endforeach

    <button type="submit">Save</button>
</form>
