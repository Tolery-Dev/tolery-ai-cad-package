<?php

namespace Tolery\AiCad\Livewire\Admin;

use Composer\InstalledVersions;
use Flux\DateRange;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
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
            'subscriptions_by_product' => $subscriptionsByProduct,
        ];
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.dashboard', [
            'kpis' => $this->getKpis(),
            'version' => InstalledVersions::getPrettyVersion('tolery/ai-cad') ?? 'dev',
        ]);
    }
}
