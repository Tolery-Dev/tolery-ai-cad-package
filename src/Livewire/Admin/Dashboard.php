<?php

namespace Tolery\AiCad\Livewire\Admin;

use Composer\InstalledVersions;
use Flux\DateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\SubscriptionPrice;
use Tolery\AiCad\Models\SubscriptionProduct;

class Dashboard extends Component
{
    public ?DateRange $range = null;

    public function mount(): void
    {
        $this->range = DateRange::thisMonth();
    }

    /**
     * @return array{
     *     purchase_revenue: float,
     *     subscription_revenue: float,
     *     total_revenue: float,
     *     purchase_count: int,
     *     conversation_count: int,
     *     download_count: int,
     *     subscription_count: int,
     *     trialing_count: int,
     *     paying_count: int,
     *     at_risk_count: int,
     *     subscriptions_by_product: array<string, int>,
     *     deltas: array{total_revenue: ?float, subscription_revenue: ?float, purchase_revenue: ?float, conversation_count: ?int, download_count: ?int}
     * }
     */
    public function getKpis(): array
    {
        $startDate = $this->range?->start();
        $endDate = $this->range?->end();

        // When no range is set, default to current month
        if ($startDate === null && $endDate === null && $this->range === null) {
            $startDate = now()->startOfMonth();
            $endDate = now();
        }

        // "Tout le temps" : start()/end() both return null — no date filter applied
        $hasDateFilter = $startDate !== null && $endDate !== null;

        $current = $this->windowMetrics($startDate, $endDate, $hasDateFilter);

        // Comparable previous window (same length, immediately before) for deltas.
        // Only computed when a finite period is selected.
        $previous = null;
        if ($hasDateFilter) {
            $duration = (int) $startDate->diffInSeconds($endDate);
            $previous = $this->windowMetrics(
                $startDate->copy()->subSeconds($duration),
                $startDate->copy(),
                true,
            );
        }

        // Current snapshot (independent of the selected period)
        $activeSubscriptions = Subscription::query()
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->with('items')
            ->get();

        $subscriptionsByProduct = $this->groupByProduct($activeSubscriptions);

        // À risque : abonné *payant* dont la résiliation est programmée mais encore en
        // période de grâce. On exclut les essais : un abonnement en essai sans moyen de
        // paiement reçoit cancel_at_period_end=true de Stripe (auto-annulation en fin
        // d'essai), ce qui pose un ends_at futur — mais ce n'est pas un client qui churn.
        $atRiskCount = Subscription::query()
            ->where('type', 'default')
            ->where('stripe_status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->count();

        $currentTotal = $current['purchase_amount'] + $current['subscription_amount'];
        $previousTotal = $previous !== null
            ? $previous['purchase_amount'] + $previous['subscription_amount']
            : null;

        return [
            'purchase_revenue' => $current['purchase_amount'] / 100,
            'subscription_revenue' => $current['subscription_amount'] / 100,
            'total_revenue' => $currentTotal / 100,
            'purchase_count' => $current['purchase_count'],
            'conversation_count' => $current['conversation_count'],
            'download_count' => $current['download_count'],
            'subscription_count' => $activeSubscriptions->count(),
            'trialing_count' => $activeSubscriptions->where('stripe_status', 'trialing')->count(),
            'paying_count' => $activeSubscriptions->where('stripe_status', 'active')->count(),
            'at_risk_count' => $atRiskCount,
            'subscriptions_by_product' => $subscriptionsByProduct,
            'deltas' => [
                'total_revenue' => $this->deltaPct($previousTotal, $currentTotal),
                'subscription_revenue' => $this->deltaPct($previous['subscription_amount'] ?? null, $current['subscription_amount']),
                'purchase_revenue' => $this->deltaPct($previous['purchase_amount'] ?? null, $current['purchase_amount']),
                'conversation_count' => $previous !== null ? $current['conversation_count'] - $previous['conversation_count'] : null,
                'download_count' => $previous !== null ? $current['download_count'] - $previous['download_count'] : null,
            ],
        ];
    }

    /**
     * Period-dependent revenue and activity metrics for a single time window.
     *
     * @return array{purchase_amount: int, subscription_amount: int, purchase_count: int, conversation_count: int, download_count: int}
     */
    private function windowMetrics(?\Carbon\Carbon $start, ?\Carbon\Carbon $end, bool $hasDateFilter): array
    {
        $purchaseQuery = FilePurchase::query();
        $chatQuery = Chat::query();
        $downloadQuery = ChatDownload::query();
        // Revenue only counts *paying* subscriptions. Trials are excluded: no payment
        // has been collected yet, so a trialing plan must not inflate "Revenus abonnement".
        $subscriptionsQuery = Subscription::query()
            ->where('type', 'default')
            ->where('stripe_status', 'active');

        if ($hasDateFilter) {
            $purchaseQuery->whereBetween('purchased_at', [$start, $end]);
            $chatQuery->whereBetween('created_at', [$start, $end]);
            $downloadQuery->whereBetween('downloaded_at', [$start, $end]);
            $subscriptionsQuery->whereBetween('created_at', [$start, $end]);
        }

        // Subscription revenue: sum each active subscription's plan amount,
        // resolved in one batch to avoid N+1.
        $subscriptions = $subscriptionsQuery->get(['id', 'stripe_price']);
        $priceAmounts = SubscriptionPrice::query()
            ->whereIn('stripe_price_id', $subscriptions->pluck('stripe_price')->filter()->unique()->all())
            ->pluck('amount', 'stripe_price_id');

        $subscriptionAmount = (int) $subscriptions->sum(
            /** @phpstan-ignore-next-line - stripe_price is a dynamic Cashier attribute */
            fn (Subscription $s) => (int) ($priceAmounts[$s->stripe_price] ?? 0)
        );

        return [
            'purchase_amount' => (int) $purchaseQuery->sum('amount'),
            'subscription_amount' => $subscriptionAmount,
            'purchase_count' => $purchaseQuery->count(),
            'conversation_count' => $chatQuery->count(),
            'download_count' => $downloadQuery->count(),
        ];
    }

    /**
     * Count active/trialing subscriptions grouped by product name.
     *
     * @param  Collection<int, Subscription>  $activeSubscriptions
     * @return array<string, int>
     */
    private function groupByProduct(Collection $activeSubscriptions): array
    {
        $productNames = SubscriptionProduct::query()->pluck('name', 'stripe_id');

        $grouped = [];
        foreach ($activeSubscriptions as $subscription) {
            /** @var SubscriptionItem|null $item */
            $item = $subscription->items->first();
            if ($item === null) {
                continue;
            }

            /** @phpstan-ignore-next-line - stripe_product is a dynamic Cashier attribute */
            $productName = $productNames[$item->stripe_product] ?? 'Inconnu';
            $grouped[$productName] = ($grouped[$productName] ?? 0) + 1;
        }

        return $grouped;
    }

    /**
     * Percentage change between two amounts, or null when there is no comparable base.
     */
    private function deltaPct(?int $previous, int $current): ?float
    {
        if ($previous === null || $previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Teams currently on a free trial, paired with their plan and trial end date.
     *
     * Each row: array{team_name: string, product_name: string, trial_ends_at: ?Carbon, started_at: ?Carbon, days_left: ?int}
     */
    public function getTrialingSubscriptions(): Collection
    {
        $subscriptions = Subscription::query()
            ->where('type', 'default')
            ->where('stripe_status', 'trialing')
            ->with('items')
            ->orderBy('trial_ends_at')
            ->get();

        /** @var array<int, string> $teamNames */
        $teamNames = ChatTeam::query()
            ->whereIn('id', $subscriptions->pluck('team_id')->filter()->unique()->all())
            ->pluck('name', 'id')
            ->all();

        /** @var array<string, string> $productNames */
        $productNames = SubscriptionProduct::query()
            ->pluck('name', 'stripe_id')
            ->all();

        $rows = [];

        foreach ($subscriptions as $subscription) {
            $teamId = (int) $subscription->getAttribute('team_id');

            $firstItem = $subscription->items->first();
            $stripeProductId = (string) $firstItem?->getAttribute('stripe_product');

            /** @var Carbon|null $trialEndsAt */
            $trialEndsAt = $subscription->getAttribute('trial_ends_at');
            /** @var Carbon|null $startedAt */
            $startedAt = $subscription->getAttribute('created_at');

            $rows[] = [
                'team_name' => $teamNames[$teamId] ?? 'Inconnu',
                'product_name' => $productNames[$stripeProductId] ?? 'Inconnu',
                'trial_ends_at' => $trialEndsAt,
                'started_at' => $startedAt,
                'days_left' => $trialEndsAt !== null
                    ? (int) now()->startOfDay()->diffInDays($trialEndsAt->copy()->startOfDay(), false)
                    : null,
            ];
        }

        return collect($rows);
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.dashboard', [
            'kpis' => $this->getKpis(),
            'trialingSubscriptions' => $this->getTrialingSubscriptions(),
            'version' => InstalledVersions::getPrettyVersion('tolery/ai-cad') ?? 'dev',
        ]);
    }
}
