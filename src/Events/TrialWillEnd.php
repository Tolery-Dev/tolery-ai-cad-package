<?php

namespace Tolery\AiCad\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tolery\AiCad\Models\ChatTeam;

/**
 * Fired when Stripe sends `customer.subscription.trial_will_end`,
 * 3 days before the trial ends. Consumers can subscribe to send
 * reminder emails or in-app notifications.
 */
class TrialWillEnd
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ChatTeam $team,
        public Carbon $trialEndsAt,
    ) {}
}
