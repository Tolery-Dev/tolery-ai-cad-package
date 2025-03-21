<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tolery\AiCad\Contracts\Limit as LimitContract;
use Tolery\AiCad\Enum\ResetFrequency;
use Tolery\AiCad\Exceptions\InvalidLimitResetFrequencyValue;
use Tolery\AiCad\Exceptions\LimitDoesNotExist;
use Tolery\AiCad\LimitManager;
use Tolery\AiCad\Traits\RefreshCache;

/**
 * @property int $id
 * @property string $name
 * @property string|null $plan
 * @property int $allowed_amount
 * @property string|null $allowed_amount_unit
 * @property string|null $reset_frequency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ?HasLimit $pivot
 */
class Limit extends Model implements LimitContract
{
    use RefreshCache, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('ai-cad.usage-limiter.tables.limits') ?: parent::getTable();
    }

    public static function create(array $data): LimitContract
    {
        return static::updateOrCreate($data, true);
    }

    public static function updateOrCreate(array $data, bool $throw = false): LimitContract
    {
        $data = static::validateArgs($data);

        $limit = app(LimitManager::class)->getLimit($data);

        if (! $limit) {
            return static::query()->create($data);
        }

        static::query()->where('id', $limit->id)->update($data);

        return $limit;
    }

    protected static function validateArgs(array $data): array
    {
        if (! Arr::has($data, ['name', 'allowed_amount'])) {
            throw new InvalidArgumentException('"name" and "allowed_amount" keys do not exist on the array.');
        }

        if (! is_numeric($data['allowed_amount']) || $data['allowed_amount'] < 0) {
            throw new InvalidArgumentException('"allowed_amount" should be a float|int type and greater than or equal to 0.');
        }

        if (
            Arr::has($data, ['reset_frequency']) &&
            filled($data['reset_frequency']) &&
            ! in_array($data['reset_frequency'], ResetFrequency::values())
        ) {
            throw new InvalidLimitResetFrequencyValue;
        }

        if (isset($data['plan']) && blank($data['plan'])) {
            unset($data['plan']);
        }

        return $data;
    }

    /**
     * @throws LimitDoesNotExist
     */
    public static function findByName(string|Limit $name, ?string $plan = null): Limit
    {
        if (is_object($name)) {
            return $name;
        }

        $limit = app(LimitManager::class)->getLimit(compact('name', 'plan'));

        if (! $limit) {
            throw new LimitDoesNotExist($name, $plan);
        }

        return $limit;
    }

    /**
     * @throws LimitDoesNotExist
     */
    public static function findById(int|LimitContract $id): LimitContract
    {
        if (is_object($id)) {
            return $id;
        }

        $limit = app(LimitManager::class)->getLimit(compact('id'));

        if (! $limit) {
            throw new LimitDoesNotExist((string) $id);
        }

        return $limit;
    }

    public function incrementBy(float|int $amount = 1.0): bool
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('"amount" should be greater than 0.');
        }

        $this->allowed_amount += $amount;

        return $this->save();
    }

    public function decrementBy(float|int $amount = 1.0): bool
    {
        $this->allowed_amount -= $amount;

        if ($this->allowed_amount < 0) {
            throw new InvalidArgumentException('"allowed_amount" should be greater than or equal to 0.');
        }

        return $this->save();
    }

    public function getResetFrequencyOptions(): Collection
    {
        return collect(ResetFrequency::values());
    }
}
