<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.step-messages.index') }}">
        ToleryCad - Modifier message d'etape
    </x-slot>

    <livewire:ai-cad-admin-step-message-form :stepMessage="$stepMessage" />
</x-layout.app>
