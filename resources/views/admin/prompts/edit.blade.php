<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.prompts.index') }}">
        ToleryCad - Modifier prompt
    </x-slot>

    <livewire:ai-cad-admin-predefined-prompt-form :prompt="$prompt" />
</x-layout.app>
