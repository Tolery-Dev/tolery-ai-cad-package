<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Tolery\AiCad\Traits\HasLimits;
use Tolery\AiCad\Traits\HasSubscription;

abstract class ChatTeam extends Model
{
    use HasLimits, HasSubscription;
}
