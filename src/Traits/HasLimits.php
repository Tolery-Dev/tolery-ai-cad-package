<?php

namespace Tolery\AiCad\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Throwable;
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

                $limit = new Limit([
                    'used_amount' => $usedAmount,
                    'last_reset' => now(),
                    'next_reset' => $product->frequency->addTime(now()),
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
}
