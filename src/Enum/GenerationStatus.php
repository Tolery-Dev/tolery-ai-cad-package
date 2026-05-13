<?php

namespace Tolery\AiCad\Enum;

enum GenerationStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Statuses for which a new generation cannot be started on the same chat.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::PENDING, self::RUNNING => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }
}
