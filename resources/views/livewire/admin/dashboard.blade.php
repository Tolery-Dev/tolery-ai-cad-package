<div>
    {{-- Period Selector --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:radio.group wire:model.live="period" variant="segmented">
            <flux:radio value="day" label="Jour" />
            <flux:radio value="week" label="Semaine" />
            <flux:radio value="month" label="Mois" />
            <flux:radio value="year" label="Année" />
        </flux:radio.group>
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
        <flux:button href="{{ route('ai-cad.admin.downloads.index') }}" variant="outline" class="justify-start">
            <flux:icon name="arrow-down-tray" class="mr-2" />
            Voir les téléchargements
        </flux:button>
        <flux:button href="{{ route('ai-cad.admin.prompts.index') }}" variant="outline" class="justify-start">
            <flux:icon name="document-text" class="mr-2" />
            Gérer les prompts
        </flux:button>
    </div>
</div>
