<?php

namespace Tolery\AiCad\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Online-presence detection for ToleryCAD users.
 *
 * Backed by the host application's `users.last_seen_at` column, which is kept
 * fresh by a middleware on every authenticated request (see mn-tolery's
 * `TrackUserActivity`). Used to suppress redundant email notifications when
 * the user is already watching the chatbot UI — the database (cloche)
 * notification is enough in that case.
 */
class UserPresence
{
    /**
     * Whether the given user can be considered "currently on the app".
     *
     * Returns false defensively when:
     *  - the user is null,
     *  - the host User model doesn't carry a `last_seen_at` attribute,
     *  - the column has never been populated (NULL).
     *
     * The freshness window is configurable via
     * `ai-cad.notifications.online_threshold_seconds` (default 30 seconds),
     * intentionally generous to cover the host middleware throttle.
     */
    public static function isOnline(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        $lastSeenAt = $user->last_seen_at ?? null;

        if ($lastSeenAt === null) {
            return false;
        }

        if (! $lastSeenAt instanceof DateTimeInterface) {
            try {
                $lastSeenAt = Carbon::parse((string) $lastSeenAt);
            } catch (\Throwable) {
                return false;
            }
        }

        $thresholdSeconds = (int) config('ai-cad.notifications.online_threshold_seconds', 30);

        return Carbon::instance($lastSeenAt)->diffInSeconds(Carbon::now(), true) <= $thresholdSeconds;
    }
}
