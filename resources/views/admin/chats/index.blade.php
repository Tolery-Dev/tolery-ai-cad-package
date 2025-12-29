<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.dashboard') }}">
        ToleryCad - Conversations
    </x-slot>

    <livewire:ai-cad-admin-chat-table />
</x-layout.app>
