<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.chats.index') }}">
        ToleryCad - {{ $chat->name ?: 'Conversation #'.$chat->id }}
    </x-slot>

    <livewire:ai-cad-admin-chat-detail :chat="$chat" />
</x-layout.app>
