<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.dashboard') }}">
        ToleryCad - Messages d'etape
    </x-slot>

    <div class="mb-4 flex justify-end">
        <flux:button href="{{ route('ai-cad.admin.step-messages.create') }}" icon="plus">
            Nouveau message d'etape
        </flux:button>
    </div>

    <livewire:ai-cad-admin-step-message-table />
</x-layout.app>
