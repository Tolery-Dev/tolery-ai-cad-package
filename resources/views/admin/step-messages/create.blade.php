<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.step-messages.index') }}">
        ToleryCad - Nouveau message d'etape
    </x-slot>

    <livewire:ai-cad-admin-step-message-form />
</x-layout.app>
