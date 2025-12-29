<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.dashboard') }}">
        ToleryCad - Achats de fichiers
    </x-slot>

    <livewire:ai-cad-admin-file-purchase-table />
</x-layout.app>
