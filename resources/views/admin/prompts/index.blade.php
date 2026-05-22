<x-layout.admin.tolerycad title="ToleryCAD - Prompts prédéfinis">
    <div class="mb-4 flex justify-end">
        <flux:button href="{{ route('ai-cad.admin.prompts.create') }}" icon="plus">
            Nouveau prompt
        </flux:button>
    </div>

    <livewire:ai-cad-admin-predefined-prompt-table />
</x-layout.admin.tolerycad>
