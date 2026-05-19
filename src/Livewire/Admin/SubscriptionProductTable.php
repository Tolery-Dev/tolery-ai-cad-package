<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Services\AiCadStripe;
use Tolery\AiCad\Services\StripeSyncService;

class SubscriptionProductTable extends Component
{
    public ?string $syncMessage = null;

    /** @var 'success'|'error'|null */
    public ?string $syncStatus = null;

    public function sync(StripeSyncService $syncService): void
    {
        try {
            $result = $syncService->syncProductsFromStripe();

            $message = "{$result['products']} produit(s) et {$result['prices']} prix synchronisés.";

            if (! empty($result['deleted_products'])) {
                $message .= " {$result['deleted_products']} produit(s) obsolète(s) supprimé(s).";
            }

            if (! empty($result['errors'])) {
                $this->syncStatus = 'error';
                $this->syncMessage = $message.' Erreurs : '.implode(' ; ', $result['errors']);
            } else {
                $this->syncStatus = 'success';
                $this->syncMessage = $message;
            }
        } catch (ApiErrorException $e) {
            $this->syncStatus = 'error';
            $this->syncMessage = 'Erreur API Stripe : '.$e->getMessage();
        } catch (\Throwable $e) {
            $this->syncStatus = 'error';
            $this->syncMessage = 'Erreur : '.$e->getMessage();
        }

        unset($this->catalog);
    }

    /**
     * Live Stripe catalogue paired with the locally synced products.
     *
     * @return array{products: array<int, array<string, mixed>>, error: ?string}
     */
    #[Computed]
    public function catalog(): array
    {
        $aiCadStripe = app(AiCadStripe::class);

        try {
            $stripeProducts = $aiCadStripe->listProducts();
        } catch (\Throwable $e) {
            return ['products' => [], 'error' => $e->getMessage()];
        }

        /** @var Collection<string, SubscriptionProduct> $localProducts */
        $localProducts = SubscriptionProduct::query()->get()->keyBy('stripe_id');

        $products = [];

        foreach ($stripeProducts->data as $stripeProduct) {
            try {
                $prices = $aiCadStripe->listPrices($stripeProduct->id)->data;
            } catch (\Throwable $e) {
                $prices = [];
            }

            $local = $localProducts->get($stripeProduct->id);
            $images = $stripeProduct->images ?? [];

            $products[] = [
                'id' => $stripeProduct->id,
                'name' => $stripeProduct->name,
                'description' => $stripeProduct->description,
                'image' => ! empty($images) ? $images[0] : null,
                'active' => (bool) $stripeProduct->active,
                'prices' => $this->mapPrices($prices),
                'synced' => $local !== null,
                'out_of_sync' => $local !== null && $this->productOutOfSync($stripeProduct, $local),
                'last_synced_at' => $local?->updated_at,
            ];
        }

        return ['products' => $products, 'error' => null];
    }

    /**
     * @param  array<int, Price>  $prices
     * @return array<int, array{formatted: string, interval_label: string}>
     */
    protected function mapPrices(array $prices): array
    {
        $intervalLabels = [
            'day' => 'jour',
            'week' => 'semaine',
            'month' => 'mois',
            'year' => 'an',
        ];

        $mapped = [];

        foreach ($prices as $price) {
            if ($price->type !== 'recurring' || $price->recurring === null) {
                continue;
            }

            $interval = $price->recurring->interval;

            $mapped[] = [
                'formatted' => Number::currency(
                    ($price->unit_amount ?? 0) / 100,
                    strtoupper($price->currency),
                    'fr',
                ),
                'interval_label' => $intervalLabels[$interval] ?? $interval,
            ];
        }

        return $mapped;
    }

    protected function productOutOfSync(Product $stripeProduct, SubscriptionProduct $local): bool
    {
        $images = $stripeProduct->images ?? [];
        $stripeImage = ! empty($images) ? $images[0] : null;

        return $local->name !== $stripeProduct->name
            || (string) $local->description !== (string) ($stripeProduct->description ?? '')
            || $local->image_url !== $stripeImage
            || $local->active !== (bool) $stripeProduct->active;
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.subscription-product-table');
    }
}
