<?php

namespace Tolery\AiCad\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Models\Limit;

trait HasLimits
{
    public function limits(): HasMany
    {
        return $this->hasMany(Limit::class);
    }

    /**
     * @throws Throwable
     */
    public function setLimit(float|int $usedAmount = 0.0): bool
    {
        $product = $this->getSubscriptionProduct();

        if ($product) {

            DB::transaction(function () use ($product, $usedAmount) {

                $frequency = $this->determineFrequency($product);

                $limit = new Limit([
                    'used_amount' => $usedAmount,
                    'start_date' => now(),
                    'end_date' => $frequency->addTime(now()),
                ]);
                $limit->product()->associate($product);
                $limit->team()->associate($this);
                $limit->save();
            });

            return true;
        }

        return false;
    }

    public function unsetLimit(): void
    {
        $this->limits()->delete();
    }

    protected function determineFrequency($product): ResetFrequency
    {
        if ($product->frequency) {
            return $product->frequency;
        }

        try {
            $subscription = $this->subscription();

            if ($subscription) {
                $subscription->loadMissing('items');
                $items = $subscription->items;

                if ($items->isNotEmpty()) {
                    /** @var \Laravel\Cashier\SubscriptionItem $item */
                    $item = $items->first();
                    $stripePrice = $item->asStripeSubscriptionItem()->price;
                    $interval = $stripePrice->recurring->interval ?? null;

                    if ($interval === 'year') {
                        return ResetFrequency::YEARLY;
                    }

                    if ($interval === 'month') {
                        return ResetFrequency::MONTHLY;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error determining subscription frequency', [
                'team_id' => $this->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::warning('Unable to determine subscription frequency, defaulting to MONTHLY', [
            'team_id' => $this->id,
            'product_id' => $product->id,
        ]);

        return ResetFrequency::MONTHLY;
    }
}
