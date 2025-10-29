<?php

namespace Tolery\AiCad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Tolery\AiCad\Traits\HasLimits;
use Tolery\AiCad\Traits\HasSubscription;

class ChatTeam extends Model
{
    use Billable;
    use HasFactory;
    use HasLimits;
    use HasSubscription;

    protected $table = 'teams';

    protected $guarded = [];

    public function getForeignKey(): string
    {
        return 'team_id';
    }
}
