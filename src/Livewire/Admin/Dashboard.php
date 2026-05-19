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
     *     subscriptions_by_product: array<string, int>
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

        // Achats unitaires
        $purchaseQuery = FilePurchase::query();
        if ($hasDateFilter) {
            $purchaseQuery->whereBetween('purchased_at', [$startDate, $endDate]);
        }
        $purchaseAmount = (clone $purchaseQuery)->sum('amount');
        $purchaseCount = (clone $purchaseQuery)->count();

        // Abonnements créés sur la période
        $subscriptionsQuery = Subscription::query()
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'trialing']);
        if ($hasDateFilter) {
            $subscriptionsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $subscriptionsOnPeriod = $subscriptionsQuery->get();

        // Calculer le revenu des abonnements
        $subscriptionRevenue = 0;
        foreach ($subscriptionsOnPeriod as $subscription) {
            /** @phpstan-ignore-next-line - stripe_price is a dynamic attribute from Cashier */
            $stripePrice = $subscription->stripe_price;
            $price = SubscriptionPrice::where('stripe_price_id', $stripePrice)->first();
            if ($price) {
                $subscriptionRevenue += $price->amount;
            }
        }

        // Abonnements actifs actuels (pas limités à la période)
        $activeSubscriptions = Subscription::query()
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->get();

        // Grouper par produit
        $subscriptionsByProduct = [];
        foreach ($activeSubscriptions as $subscription) {
            /** @var SubscriptionItem|null $item */
            $item = $subscription->items()->first();
            if ($item) {
                /** @phpstan-ignore-next-line - stripe_product is a dynamic attribute from Cashier */
                $stripeProduct = $item->stripe_product;
                $product = SubscriptionProduct::where('stripe_id', $stripeProduct)->first();
                $productName = $product->name ?? 'Inconnu';
                $subscriptionsByProduct[$productName] = ($subscriptionsByProduct[$productName] ?? 0) + 1;
            }
        }

        $chatQuery = Chat::query();
        $downloadQuery = ChatDownload::query();
        if ($hasDateFilter) {
            $chatQuery->whereBetween('created_at', [$startDate, $endDate]);
            $downloadQuery->whereBetween('downloaded_at', [$startDate, $endDate]);
        }

        return [
            'purchase_revenue' => $purchaseAmount / 100,
            'subscription_revenue' => $subscriptionRevenue / 100,
            'total_revenue' => ($purchaseAmount + $subscriptionRevenue) / 100,
            'purchase_count' => $purchaseCount,
            'conversation_count' => $chatQuery->count(),
            'download_count' => $downloadQuery->count(),
            'subscription_count' => $activeSubscriptions->count(),
            'trialing_count' => $activeSubscriptions->where('stripe_status', 'trialing')->count(),
            'subscriptions_by_product' => $subscriptionsByProduct,
        ];
    }

    /**
     * Teams currently on a free trial, paired with their plan and trial end date.
     *
     * @return Collection<int, array{team_name: string, product_name: string, trial_ends_at: ?Carbon, started_at: ?Carbon, days_left: ?int}>
     */
    public function getTrialingSubscriptions(): Collection
    {
        $subscriptions = Subscription::query()
            ->where('type', 'default')
            ->where('stripe_status', 'trialing')
            ->with('items')
            ->orderBy('trial_ends_at')
            ->get();

        /** @var Collection<int, ChatTeam> $teams */
        $teams = ChatTeam::query()
            ->whereIn('id', $subscriptions->pluck('team_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        return $subscriptions->map(function (Subscription $subscription) use ($teams): array {
            /** @var SubscriptionItem|null $item */
            $item = $subscription->items->first();

            $product = $item !== null
                /** @phpstan-ignore-next-line - stripe_product is a dynamic attribute from Cashier */
                ? SubscriptionProduct::where('stripe_id', $item->stripe_product)->first()
                : null;

            $trialEndsAt = $subscription->trial_ends_at;

            return [
                'team_name' => $teams->get($subscription->team_id)?->name ?? 'Inconnu',
                'product_name' => $product?->name ?? 'Inconnu',
                'trial_ends_at' => $trialEndsAt,
                'started_at' => $subscription->created_at,
                'days_left' => $trialEndsAt !== null
                    ? (int) now()->startOfDay()->diffInDays($trialEndsAt->copy()->startOfDay(), false)
                    : null,
            ];
        });
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
