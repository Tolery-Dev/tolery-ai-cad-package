<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Livewire\Attributes\Url;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Models\SubscriptionPrice;
use Tolery\AiCad\Models\SubscriptionProduct;

class Dashboard extends Component
{
    #[Url]
    public string $period = 'month';

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
        $startDate = match ($this->period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        // Achats unitaires
        $purchaseAmount = FilePurchase::where('purchased_at', '>=', $startDate)->sum('amount');
        $purchaseCount = FilePurchase::where('purchased_at', '>=', $startDate)->count();

        // Abonnements créés sur la période
        $subscriptionsOnPeriod = Subscription::query()
            ->where('type', 'tolerycad')
            ->where('created_at', '>=', $startDate)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->get();

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
            ->where('type', 'tolerycad')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->get();

        // Grouper par produit
        $subscriptionsByProduct = [];
        foreach ($activeSubscriptions as $subscription) {
            /** @var \Laravel\Cashier\SubscriptionItem|null $item */
            $item = $subscription->items()->first();
            if ($item) {
                /** @phpstan-ignore-next-line - stripe_product is a dynamic attribute from Cashier */
                $stripeProduct = $item->stripe_product;
                $product = SubscriptionProduct::where('stripe_id', $stripeProduct)->first();
                $productName = $product->name ?? 'Inconnu';
                $subscriptionsByProduct[$productName] = ($subscriptionsByProduct[$productName] ?? 0) + 1;
            }
        }

        return [
            'purchase_revenue' => $purchaseAmount / 100,
            'subscription_revenue' => $subscriptionRevenue / 100,
            'total_revenue' => ($purchaseAmount + $subscriptionRevenue) / 100,
            'purchase_count' => $purchaseCount,
            'conversation_count' => Chat::where('created_at', '>=', $startDate)->count(),
            'download_count' => ChatDownload::where('downloaded_at', '>=', $startDate)->count(),
            'subscription_count' => $activeSubscriptions->count(),
            'subscriptions_by_product' => $subscriptionsByProduct,
        ];
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.dashboard', [
            'kpis' => $this->getKpis(),
        ]);
    }
}
