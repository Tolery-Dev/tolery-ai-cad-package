<div>
    {{-- Header with Period Selector --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading level="1" size="xl">Dashboard ToleryCAD</flux:heading>
                <flux:badge color="zinc" size="sm">v{{ $version }}</flux:badge>
            </div>
            <flux:text class="text-gray-500">Statistiques et revenus</flux:text>
        </div>
        <flux:date-picker
            wire:model.live="range"
            mode="range"
            with-presets
            presets="today yesterday last7Days thisWeek thisMonth lastMonth yearToDate allTime custom"
            locale="fr"
        >
            <x-slot name="trigger">
                <flux:date-picker.button />
            </x-slot>
        </flux:date-picker>
    </div>

    {{-- Revenue Summary --}}
    <flux:heading level="2" size="lg" class="mb-4">Revenus</flux:heading>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-8">
        <flux:card class="bg-green-50 dark:bg-green-900/20">
            <flux:heading level="3" size="sm">Total revenus ToleryCAD</flux:heading>
            <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ number_format($kpis['total_revenue'], 2, ',', ' ') }} €</div>
            <flux:text class="text-sm text-gray-500 mt-1">Sur la période sélectionnée</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading level="3" size="sm">Revenus abonnements</flux:heading>
            <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($kpis['subscription_revenue'], 2, ',', ' ') }} €</div>
            <flux:text class="text-sm text-gray-500 mt-1">Nouveaux abonnements sur la période</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading level="3" size="sm">Revenus achats unitaires</flux:heading>
            <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($kpis['purchase_revenue'], 2, ',', ' ') }} €</div>
            <flux:text class="text-sm text-gray-500 mt-1">{{ $kpis['purchase_count'] }} achat(s) de fichier</flux:text>
        </flux:card>
    </div>

    {{-- Subscriptions --}}
    <flux:heading level="2" size="lg" class="mb-4">Abonnements actifs</flux:heading>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
        <flux:card class="bg-purple-50 dark:bg-purple-900/20">
            <flux:heading level="3" size="sm">Total abonnés</flux:heading>
            <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">{{ $kpis['subscription_count'] }}</div>
            <flux:text class="text-sm text-gray-500 mt-1">actifs et essais inclus</flux:text>
        </flux:card>

        <flux:card class="bg-amber-50 dark:bg-amber-900/20">
            <flux:heading level="3" size="sm">Essais gratuits en cours</flux:heading>
            <div class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $kpis['trialing_count'] }}</div>
            <flux:text class="text-sm text-gray-500 mt-1">compte(s) en période d'essai</flux:text>
        </flux:card>

        @forelse ($kpis['subscriptions_by_product'] as $productName => $count)
            <flux:card>
                <flux:heading level="3" size="sm">{{ $productName }}</flux:heading>
                <div class="mt-2 text-3xl font-bold">{{ $count }}</div>
                <flux:text class="text-sm text-gray-500 mt-1">abonné(s)</flux:text>
            </flux:card>
        @empty
            <flux:card class="md:col-span-3">
                <flux:text class="text-gray-500">Aucun abonnement actif</flux:text>
            </flux:card>
        @endforelse
    </div>

    {{-- Comptes en essai gratuit --}}
    <flux:heading level="2" size="lg" class="mb-4">Comptes en essai gratuit</flux:heading>
    <div class="mb-8 overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700">
        @if ($trialingSubscriptions->isEmpty())
            <div class="p-6">
                <flux:text class="text-gray-500">Aucun compte en essai gratuit actuellement.</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-gray-700">
                            <th class="px-4 py-3 font-medium">Équipe</th>
                            <th class="px-4 py-3 font-medium">Produit</th>
                            <th class="px-4 py-3 font-medium">Début d'essai</th>
                            <th class="px-4 py-3 font-medium">Fin d'essai</th>
                            <th class="px-4 py-3 font-medium">Jours restants</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trialingSubscriptions as $trial)
                            <tr class="border-b border-gray-50 last:border-0 dark:border-gray-800">
                                <td class="px-4 py-3 font-medium">{{ $trial['team_name'] }}</td>
                                <td class="px-4 py-3">{{ $trial['product_name'] }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $trial['started_at']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $trial['trial_ends_at']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($trial['days_left'] === null)
                                        <span class="text-gray-400">-</span>
                                    @elseif ($trial['days_left'] < 0)
                                        <flux:badge size="sm" color="red">Expiré</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="{{ $trial['days_left'] <= 3 ? 'amber' : 'green' }}">
                                            {{ $trial['days_left'] }} jour(s)
                                        </flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Activity KPIs --}}
    <flux:heading level="2" size="lg" class="mb-4">Activité</flux:heading>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 mb-8">
        <flux:card>
            <flux:heading level="3" size="sm">Conversations</flux:heading>
            <div class="mt-2 text-3xl font-bold">{{ $kpis['conversation_count'] }}</div>
            <flux:text class="text-sm text-gray-500 mt-1">Sur la période sélectionnée</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading level="3" size="sm">Téléchargements</flux:heading>
            <div class="mt-2 text-3xl font-bold">{{ $kpis['download_count'] }}</div>
            <flux:text class="text-sm text-gray-500 mt-1">Sur la période sélectionnée</flux:text>
        </flux:card>
    </div>

    {{-- Quick Links --}}
    <flux:heading level="2" size="lg" class="mb-4">Accès rapide</flux:heading>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <flux:button href="{{ route('ai-cad.admin.purchases.index') }}" variant="outline" class="justify-start">
            <flux:icon name="banknotes" class="mr-2" />
            Voir les achats
        </flux:button>
        <flux:button href="{{ route('ai-cad.admin.chats.index') }}" variant="outline" class="justify-start">
            <flux:icon name="chat-bubble-left-right" class="mr-2" />
            Voir les conversations
        </flux:button>
        <flux:button href="{{ route('ai-cad.admin.products.index') }}" variant="outline" class="justify-start">
            <flux:icon name="cube" class="mr-2" />
            Produits Stripe
        </flux:button>
        <flux:button href="{{ route('ai-cad.admin.downloads.index') }}" variant="outline" class="justify-start">
            <flux:icon name="arrow-down-tray" class="mr-2" />
            Voir les téléchargements
        </flux:button>
        <flux:button href="{{ route('ai-cad.admin.prompts.index') }}" variant="outline" class="justify-start">
            <flux:icon name="document-text" class="mr-2" />
            Gérer les prompts
        </flux:button>
        <flux:button href="{{ route('ai-cad.admin.step-messages.index') }}" variant="outline" class="justify-start">
            <flux:icon name="chat-bubble-bottom-center" class="mr-2" />
            Gérer les messages d'étapes
        </flux:button>
        @if(Route::has('admin.tolerycad.beta-testers'))
            <flux:button href="{{ route('admin.tolerycad.beta-testers') }}" variant="outline" class="justify-start">
                <flux:icon name="user-group" class="mr-2" />
                Gérer les beta testeurs
            </flux:button>
        @endif
        @if(Route::has('admin.tolerycad.dfm-error-codes'))
            <flux:button href="{{ route('admin.tolerycad.dfm-error-codes') }}" variant="outline" class="justify-start">
                <flux:icon name="exclamation-triangle" class="mr-2" />
                Gérer les codes d'erreurs DFM
            </flux:button>
        @endif
        @if(Route::has('admin.tolerycad.subscriptions'))
            <flux:button href="{{ route('admin.tolerycad.subscriptions') }}" variant="outline" class="justify-start">
                <flux:icon name="credit-card" class="mr-2" />
                Suivre les abonnements
            </flux:button>
        @endif
    </div>
</div>
