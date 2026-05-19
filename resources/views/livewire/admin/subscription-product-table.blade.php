<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading level="1" size="xl">Produits Stripe</flux:heading>
            <flux:text class="text-gray-500">Catalogue Stripe et synchronisation sur la plateforme</flux:text>
        </div>
        <flux:button
            wire:click="sync"
            wire:loading.attr="disabled"
            wire:target="sync"
            variant="primary"
            icon="arrow-path"
        >
            <span wire:loading.remove wire:target="sync">Synchroniser</span>
            <span wire:loading wire:target="sync">Synchronisation…</span>
        </flux:button>
    </div>

    {{-- Sync result --}}
    @if ($syncMessage)
        <div class="mb-6 rounded-lg border p-4 text-sm {{ $syncStatus === 'success' ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-300' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300' }}">
            {{ $syncMessage }}
        </div>
    @endif

    @php($catalog = $this->catalog)

    @if ($catalog['error'])
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
            Impossible de récupérer le catalogue Stripe : {{ $catalog['error'] }}
        </div>
    @elseif (empty($catalog['products']))
        <flux:card>
            <flux:text class="text-gray-500">Aucun produit dans le catalogue Stripe.</flux:text>
        </flux:card>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($catalog['products'] as $product)
                <flux:card class="flex flex-col">
                    {{-- Product image --}}
                    <div class="mb-4 flex h-40 items-center justify-center overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800">
                        @if ($product['image'])
                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="h-full w-full object-contain" />
                        @else
                            <flux:icon name="photo" class="h-10 w-10 text-gray-300" />
                        @endif
                    </div>

                    {{-- Name + Stripe status --}}
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <flux:heading level="3" size="sm">{{ $product['name'] }}</flux:heading>
                        @if ($product['active'])
                            <flux:badge size="sm" color="green">Actif</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Inactif</flux:badge>
                        @endif
                    </div>

                    {{-- Description --}}
                    <flux:text class="mb-3 line-clamp-3 text-sm text-gray-500">
                        {{ $product['description'] ?: 'Aucune description' }}
                    </flux:text>

                    {{-- Prices --}}
                    <div class="mb-3 space-y-1">
                        @forelse ($product['prices'] as $price)
                            <div class="text-sm font-medium">
                                {{ $price['formatted'] }}
                                <span class="text-gray-400">/ {{ $price['interval_label'] }}</span>
                            </div>
                        @empty
                            <flux:text class="text-sm text-gray-400">Aucun prix récurrent</flux:text>
                        @endforelse
                    </div>

                    {{-- Sync status --}}
                    <div class="mt-auto border-t border-gray-100 pt-3 dark:border-gray-700">
                        @if (! $product['synced'])
                            <flux:badge size="sm" color="zinc">Non synchronisé</flux:badge>
                        @elseif ($product['out_of_sync'])
                            <flux:badge size="sm" color="amber">À mettre à jour</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">Synchronisé</flux:badge>
                        @endif

                        @if ($product['last_synced_at'])
                            <flux:text class="mt-1 text-xs text-gray-400">
                                Dernière synchro : {{ $product['last_synced_at']->format('d/m/Y H:i') }}
                            </flux:text>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
