<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Tolery\AiCad\Traits\HasLimits;
use Tolery\AiCad\Traits\HasSubscription;

class ChatTeam extends Model
{
    use HasFactory;

    protected $table = 'teams';

    use Billable,
        HasLimits,
        HasSubscription;

    public function getForeignKey(): string
    {
        return 'team_id';
    }
}
