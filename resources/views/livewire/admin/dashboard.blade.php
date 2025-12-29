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

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
        <flux:card>
            <flux:heading level="3" size="sm">Revenus</flux:heading>
            <div class="mt-2 text-3xl font-bold">{{ number_format($kpis['total_revenue'], 2, ',', ' ') }} €</div>
        </flux:card>

        <flux:card>
            <flux:heading level="3" size="sm">Achats de fichiers</flux:heading>
            <div class="mt-2 text-3xl font-bold">{{ $kpis['purchase_count'] }}</div>
        </flux:card>

        <flux:card>
            <flux:heading level="3" size="sm">Conversations</flux:heading>
            <div class="mt-2 text-3xl font-bold">{{ $kpis['conversation_count'] }}</div>
        </flux:card>

        <flux:card>
            <flux:heading level="3" size="sm">Téléchargements</flux:heading>
            <div class="mt-2 text-3xl font-bold">{{ $kpis['download_count'] }}</div>
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
