<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Model;
use Tolery\AiCad\Traits\HasLimits;

abstract class ChatTeam extends Model {
    use HasLimits;
}
