<?php

namespace Tolery\AiCad\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;
use Tolery\AiCad\Contracts\Limit as LimitContract;
use Tolery\AiCad\Exceptions\LimitDoesNotExist;
use Tolery\AiCad\Exceptions\LimitNotSetOnModel;
use Tolery\AiCad\Exceptions\UsedAmountShouldBePositiveIntAndLessThanAllowedAmount;
use Tolery\AiCad\LimitManager;
use Tolery\AiCad\Models\Limit;

trait HasLimits
{
    protected static function bootHasLimits(): void
    {
        static::resolveRelationUsing(self::getLimitsRelationship(), function (Model $model) {
            return $model
                ->morphToMany(
                    Limit::class,
                    'model',
                    config('ai-cad.usage-limiter.tables.model_has_limits'),
                    'model_id',
                    config('ai-cad.usage-limiter.columns.limit_pivot_key'),
                )
                ->withPivot(['used_amount', 'last_reset', 'next_reset'])
                ->withTimestamps();
        });
    }

    /**
     * @throws Throwable
     * @throws LimitDoesNotExist
     */
    public function setLimit(string|LimitContract $name, ?string $plan = null, float|int $usedAmount = 0.0): bool
    {
        $limit = app(Limit::class)::findByName($name, $plan);

        if ($this->isLimitSet($limit)) {
            return true;
        }

        if ($usedAmount > $limit->allowed_amount) {
            throw new InvalidArgumentException('"used_amount" should always be less than or equal to the limit "allowed_amount"');
        }

        DB::transaction(function () use ($limit, $usedAmount) {
            $this->limitsRelationship()->attach([
                $limit->id => [
                    'used_amount' => $usedAmount,
                    'last_reset' => now(),
                ],
            ]);

            if ($limit->reset_frequency) {
                $this->limitsRelationship()->updateExistingPivot($limit->id, [
                    'next_reset' => app(LimitManager::class)->getNextReset($limit->reset_frequency, now()),
                ]);
            }
        });

        $this->unloadLimitsRelationship();

        return true;
    }

    /**
     * @throws LimitDoesNotExist
     */
    public function isLimitSet(string|LimitContract $name, ?string $plan = null): bool
    {
        $limit = app(Limit::class)::findByName($name, $plan);

        return $this->getModelLimits()->where('name', $limit->name)->isNotEmpty();
    }

    /**
     * @throws LimitNotSetOnModel
     */
    public function unsetLimit(string|LimitContract $name, ?string $plan = null): bool
    {
        $limit = $this->getModelLimit($name, $plan);

        $this->limitsRelationship()->detach($limit->id);

        $this->unloadLimitsRelationship();

        return true;
    }

    /**
     * @throws UsedAmountShouldBePositiveIntAndLessThanAllowedAmount
     * @throws LimitNotSetOnModel
     * @throws LimitDoesNotExist
     */
    public function useLimit(string|Limit $name, ?string $plan = null, float|int $amount = 1.0): bool
    {
        $limit = $this->getModelLimit($name, $plan);

        $newUsedAmount = $limit->pivot->used_amount + $amount;

        if ($newUsedAmount <= 0 || ! $this->ensureUsedAmountIsLessThanAllowedAmount($name, $plan, $newUsedAmount)) {
            throw new UsedAmountShouldBePositiveIntAndLessThanAllowedAmount;
        }

        $this->limitsRelationship()->updateExistingPivot($limit->id, [
            'used_amount' => $newUsedAmount,
        ]);

        $this->unloadLimitsRelationship();

        return true;
    }

    public function unuseLimit(string|LimitContract $name, ?string $plan = null, float|int $amount = 1.0): bool
    {
        $limit = $this->getModelLimit($name, $plan);

        $newUsedAmount = $limit->pivot->used_amount - $amount;

        if ($newUsedAmount < 0 || ! $this->ensureUsedAmountIsLessThanAllowedAmount($name, $plan, $newUsedAmount)) {
            throw new UsedAmountShouldBePositiveIntAndLessThanAllowedAmount;
        }

        $this->limitsRelationship()->updateExistingPivot($limit->id, [
            'used_amount' => $newUsedAmount,
        ]);

        $this->unloadLimitsRelationship();

        return true;
    }

    public function resetLimit(string|LimitContract $name, ?string $plan = null): bool
    {
        $limit = $this->getModelLimit($name, $plan);

        $this->limitsRelationship()->updateExistingPivot($limit->id, [
            'used_amount' => 0,
        ]);

        $this->unloadLimitsRelationship();

        return true;
    }

    public function hasEnoughLimit(string|LimitContract $name, ?string $plan = null): bool
    {
        $limit = $this->getModelLimit($name, $plan);

        $usedAmount = $limit->pivot->used_amount;

        return $limit->allowed_amount > $usedAmount;
    }

    public function ensureUsedAmountIsLessThanAllowedAmount(string|LimitContract $name, ?string $plan, float|int $usedAmount): bool
    {
        $limit = $this->getModelLimit($name, $plan);

        return $usedAmount <= $limit->allowed_amount;
    }

    public function usedLimit(string|LimitContract $name, ?string $plan = null): float
    {
        $limit = $this->getModelLimit($name, $plan);

        return $limit->pivot->used_amount;
    }

    public function remainingLimit(string|LimitContract $name, ?string $plan = null): float
    {
        $limit = $this->getModelLimit($name, $plan);

        return $limit->allowed_amount - $limit->pivot->used_amount;
    }

    /**
     * @throws LimitDoesNotExist
     * @throws LimitNotSetOnModel
     */
    public function getModelLimit(string|LimitContract $name, ?string $plan = null): Limit
    {
        $limit = app(Limit::class)::findByName($name, $plan);

        $modelLimit = $this->getModelLimits()->firstWhere('id', $limit->id);

        if (! $modelLimit) {
            throw new LimitNotSetOnModel($name);
        }

        return $modelLimit;
    }

    public function getModelLimits(): Collection
    {
        $relationshipName = self::getLimitsRelationship();

        $this->loadMissing($relationshipName);

        return $this->$relationshipName;
    }

    public function limitsRelationship(): MorphToMany
    {
        $relationshipName = self::getLimitsRelationship();

        return $this->$relationshipName();
    }

    public function unloadLimitsRelationship(): void
    {
        $relationshipName = self::getLimitsRelationship();

        $this->unsetRelation($relationshipName);
    }

    private static function getLimitsRelationship(): string
    {
        return config('ai-cad.usage-limiter.relationship');
    }

    public function limitUsageReport(string|Limit|null $name = null, ?string $plan = null): array
    {
        $modelLimits = ! is_null($name) ? collect([$this->getModelLimit($name, $plan)]) : $this->getModelLimits();

        return
        $modelLimits
            ->mapWithKeys(function (Limit $modelLimit) {
                return [
                    $modelLimit->name => [
                        'allowed_amount' => $modelLimit->allowed_amount,
                        'used_amount' => $modelLimit->pivot->used_amount,
                        'remaining_amount' => $modelLimit->allowed_amount - $modelLimit->pivot->used_amount,
                    ],
                ];
            })->all();
    }
}
