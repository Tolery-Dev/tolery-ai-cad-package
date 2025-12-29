<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.dashboard') }}">
        ToleryCad - Prompts prédéfinis
    </x-slot>

    <div class="mb-4 flex justify-end">
        <flux:button href="{{ route('ai-cad.admin.prompts.create') }}" icon="plus">
            Nouveau prompt
        </flux:button>
    </div>

    <livewire:ai-cad-admin-predefined-prompt-table />
</x-layout.app>
