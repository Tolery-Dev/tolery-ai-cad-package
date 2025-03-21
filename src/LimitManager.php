<?php

namespace Tolery\AiCad;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Exceptions\InvalidLimitResetFrequencyValue;
use Tolery\AiCad\Models\Limit;

/**
 * @see https://nabilhassen.com/laravel-usage-limiter-manage-rate-and-usage-limits
 */
class LimitManager
{
    private $cache;

    private Limit $limitClass;

    private int|\DateInterval $cacheExpirationTime;

    private string $cacheKey;

    private Collection $limits;

    public function __construct(Collection $limits, Limit $limitClass)
    {
        $this->limits = $limits;

        $this->limitClass = $limitClass;

        $this->initCache();
    }

    public function initCache(): void
    {
        $cacheStore = config('ai-cad.usage-limiter.cache.store');

        $this->cacheExpirationTime = config('ai-cad.usage-limiter.cache.expiration_time') ?: \DateInterval::createFromDateString('24 hours');

        $this->cacheKey = config('ai-cad.usage-limiter.cache.key');

        if ($cacheStore === 'default') {
            $this->cache = Cache::store();

            return;
        }

        if (! array_key_exists($cacheStore, config('cache.stores'))) {
            $cacheStore = 'array';
        }

        $this->cache = Cache::store($cacheStore);
    }

    public function getNextReset(string $limitResetFrequency, string|Carbon $lastReset): Carbon
    {
        if ($this->limitClass->getResetFrequencyOptions()->doesntContain($limitResetFrequency)) {
            throw new InvalidLimitResetFrequencyValue;
        }

        $lastReset = Carbon::parse($lastReset);

        return ResetFrequency::from($limitResetFrequency)->addTime($lastReset);
    }

    public function loadLimits(): void
    {
        if ($this->limits->isNotEmpty()) {
            return;
        }

        $this->limits = $this->cache->remember($this->cacheKey, $this->cacheExpirationTime, function () {
            return $this->limitClass::all([
                'id',
                'name',
                'plan',
                'allowed_amount',
                'reset_frequency',
            ]);
        });
    }

    public function getLimit(array $data)
    {
        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $plan = $data['plan'] ?? null;

        if (is_null($id) && is_null($name)) {
            throw new InvalidArgumentException('Either Limit id OR name parameters should be filled.');
        }

        $this->loadLimits();

        if (filled($id)) {
            return $this->limits->firstWhere('id', $id);
        }

        return $this
            ->limits
            ->where('name', $name)
            ->when(
                filled($plan),
                fn ($q) => $q->where('plan', $plan),
                fn ($q) => $q->whereNull('plan')
            )
            ->first();
    }

    public function getLimits(): Collection
    {
        $this->loadLimits();

        return $this->limits;
    }

    public function flushCache(): void
    {
        $this->limits = collect();

        $this->cache->forget($this->cacheKey);
    }

    public function getCacheStore()
    {
        return $this->cache->getStore();
    }
}
