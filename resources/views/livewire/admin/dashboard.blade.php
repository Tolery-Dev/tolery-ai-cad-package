@php
    /**
     * Render a period-over-period delta as a coloured Flux badge.
     * $value is a percentage (float) or an absolute count (int); null hides the badge.
     */
    $deltaColor = fn ($value) => $value === null ? 'zinc' : ($value > 0 ? 'green' : ($value < 0 ? 'red' : 'zinc'));

    $maxByProduct = collect($kpis['subscriptions_by_product'])->max() ?: 1;
@endphp

<div>
    {{-- Header with Period Selector --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading level="1" size="xl">Tableau de bord</flux:heading>
                <flux:badge color="zinc" size="sm">v{{ $version }}</flux:badge>
            </div>
            <flux:text class="text-zinc-500">Revenus, abonnements et activité de la plateforme ToleryCAD.</flux:text>
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

    {{-- Revenue KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        @php
            $revenueCards = [
                ['label' => 'Revenus totaux HT', 'value' => $kpis['total_revenue'], 'delta' => $kpis['deltas']['total_revenue'], 'icon' => 'banknotes'],
                ['label' => 'Revenus abonnement HT', 'value' => $kpis['subscription_revenue'], 'delta' => $kpis['deltas']['subscription_revenue'], 'icon' => 'arrow-path'],
                ['label' => 'Revenus achat unitaire HT', 'value' => $kpis['purchase_revenue'], 'delta' => $kpis['deltas']['purchase_revenue'], 'icon' => 'cube', 'meta' => $kpis['purchase_count'].' fichier'.($kpis['purchase_count'] > 1 ? 's' : '').' acheté'.($kpis['purchase_count'] > 1 ? 's' : '').' à l\'unité'],
            ];
        @endphp

        @foreach ($revenueCards as $card)
            <flux:card class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm font-medium text-zinc-500">{{ $card['label'] }}</flux:text>
                    <flux:icon :name="$card['icon']" class="size-4 text-zinc-400" />
                </div>
                <div class="text-2xl font-bold tabular-nums">{{ number_format($card['value'], 2, ',', ' ') }} €</div>
                @if (! empty($card['meta']))
                    <flux:text class="text-xs text-zinc-500">{{ $card['meta'] }}</flux:text>
                @endif
                <div class="mt-auto">
                    @if ($card['delta'] !== null)
                        <flux:badge :color="$deltaColor($card['delta'])" size="sm">
                            {{ $card['delta'] > 0 ? '+' : '' }}{{ number_format($card['delta'], 1, ',', ' ') }} %
                        </flux:badge>
                        <flux:text class="ml-1 text-xs text-zinc-500">vs période précédente</flux:text>
                    @else
                        <flux:text class="text-xs text-zinc-400">Sur la période sélectionnée</flux:text>
                    @endif
                </div>
            </flux:card>
        @endforeach

        <flux:card class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <flux:text class="text-sm font-medium text-zinc-500">Abonnés</flux:text>
                <flux:icon name="user-group" class="size-4 text-zinc-400" />
            </div>
            <div class="text-2xl font-bold tabular-nums">{{ $kpis['subscription_count'] }}</div>
            <div class="mt-auto flex flex-wrap gap-1.5">
                <flux:badge color="green" size="sm">{{ $kpis['paying_count'] }} payant{{ $kpis['paying_count'] > 1 ? 's' : '' }}</flux:badge>
                <flux:badge color="amber" size="sm">{{ $kpis['trialing_count'] }} en essai</flux:badge>
                @if ($kpis['at_risk_count'] > 0)
                    <flux:badge color="red" size="sm">{{ $kpis['at_risk_count'] }} à risque</flux:badge>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- Subscriptions breakdown by plan --}}
    <flux:heading level="2" size="lg" class="mb-4">Abonnements actifs par plan</flux:heading>
    <flux:card class="mb-8">
        @forelse ($kpis['subscriptions_by_product'] as $productName => $count)
            <div class="flex items-center gap-3 py-1.5">
                <span class="w-40 shrink-0 text-sm text-zinc-600 dark:text-zinc-300 truncate">{{ $productName }}</span>
                <div class="flex-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                    <div class="h-full rounded-full bg-violet-600" style="width: {{ max(4, round(($count / $maxByProduct) * 100)) }}%;"></div>
                </div>
                <span class="w-10 text-right text-sm font-medium tabular-nums">{{ $count }}</span>
            </div>
        @empty
            <flux:text class="text-zinc-500">Aucun abonnement actif.</flux:text>
        @endforelse
    </flux:card>

    {{-- Comptes en essai gratuit --}}
    <flux:heading level="2" size="lg" class="mb-4">Comptes en essai gratuit</flux:heading>
    <div class="mb-8 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        @if ($trialingSubscriptions->isEmpty())
            <div class="p-6">
                <flux:text class="text-zinc-500">Aucun compte en essai gratuit actuellement.</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-zinc-500 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium">Équipe</th>
                            <th class="px-4 py-3 font-medium">Plan</th>
                            <th class="px-4 py-3 font-medium">Début d'essai</th>
                            <th class="px-4 py-3 font-medium">Fin d'essai</th>
                            <th class="px-4 py-3 font-medium">Jours restants</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trialingSubscriptions as $trial)
                            <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                                <td class="px-4 py-3 font-medium">{{ $trial['team_name'] }}</td>
                                <td class="px-4 py-3">{{ $trial['product_name'] }}</td>
                                <td class="px-4 py-3 text-zinc-500">{{ $trial['started_at']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-zinc-500">{{ $trial['trial_ends_at']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($trial['days_left'] === null)
                                        <span class="text-zinc-400">-</span>
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @php
            $activityCards = [
                ['label' => 'Conversations CAO', 'value' => $kpis['conversation_count'], 'delta' => $kpis['deltas']['conversation_count']],
                ['label' => 'Téléchargements', 'value' => $kpis['download_count'], 'delta' => $kpis['deltas']['download_count']],
            ];
        @endphp

        @foreach ($activityCards as $card)
            <flux:card class="flex flex-col gap-2">
                <flux:text class="text-sm font-medium text-zinc-500">{{ $card['label'] }}</flux:text>
                <div class="text-2xl font-bold tabular-nums">{{ $card['value'] }}</div>
                @if ($card['delta'] !== null)
                    <div>
                        <flux:badge :color="$deltaColor($card['delta'])" size="sm">
                            {{ $card['delta'] > 0 ? '+' : '' }}{{ $card['delta'] }}
                        </flux:badge>
                        <flux:text class="ml-1 text-xs text-zinc-500">vs période précédente</flux:text>
                    </div>
                @else
                    <flux:text class="text-xs text-zinc-400">Sur la période sélectionnée</flux:text>
                @endif
            </flux:card>
        @endforeach
    </div>
</div>
