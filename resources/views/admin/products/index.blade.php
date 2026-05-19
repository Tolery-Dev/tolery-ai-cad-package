<x-layout.app>
    <x-slot:title returnUrl="{{ route('ai-cad.admin.dashboard') }}">
        ToleryCad - Produits Stripe
    </x-slot>

    <livewire:ai-cad-admin-subscription-product-table />
</x-layout.app>
