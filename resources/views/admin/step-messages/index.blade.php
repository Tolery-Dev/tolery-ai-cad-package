<x-layout.admin.tolerycad title="ToleryCAD - Messages d'étape">
    <div class="mb-4 flex justify-end">
        <flux:button href="{{ route('ai-cad.admin.step-messages.create') }}" icon="plus">
            Nouveau message d'étape
        </flux:button>
    </div>

    <livewire:ai-cad-admin-step-message-table />
</x-layout.admin.tolerycad>
