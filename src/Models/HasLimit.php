<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $used_amount
 * @property Carbon|null $last_reset
 * @property Carbon|null $next_reset
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HasLimit extends Pivot {}
