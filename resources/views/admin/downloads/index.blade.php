<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.dashboard') }}">
        ToleryCad - Téléchargements
    </x-slot>

    <livewire:ai-cad-admin-chat-download-table />
</x-layout.app>
