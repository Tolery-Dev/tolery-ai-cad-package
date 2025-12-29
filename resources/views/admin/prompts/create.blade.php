<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.prompts.index') }}">
        ToleryCad - Nouveau prompt
    </x-slot>

    <livewire:ai-cad-admin-predefined-prompt-form />
</x-layout.app>
