<?php

namespace Tolery\AiCad\Enum;

use Illuminate\Support\Carbon;

enum ResetFrequency: string
{
    case EVERY_SECOND = 'every second';
    case EVERY_MINUTE = 'every minute';
    case EVERY_HOUR = 'every hour';
    case EVERY_DAY = 'every day';
    case EVERY_WEEK = 'every week';
    case EVERY_TWO_WEEKS = 'every two weeks';
    case EVERY_MONTH = 'every month';
    case EVERY_QUARTER = 'every quarter';
    case EVERY_SIX_MONTHS = 'every six months';
    case EVERY_YEAR = 'every year';

    /**
     * Retourne toutes les valeurs de l'Enum.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function addTime(Carbon $date): Carbon
    {
        return match ($this) {
            self::EVERY_SECOND => $date->addSecond(),
            self::EVERY_MINUTE => $date->addMinute(),
            self::EVERY_HOUR => $date->addHour(),
            self::EVERY_DAY => $date->addDay(),
            self::EVERY_WEEK => $date->addWeek(),
            self::EVERY_TWO_WEEKS => $date->addWeeks(2),
            self::EVERY_MONTH => $date->addMonth(),
            self::EVERY_QUARTER => $date->addQuarter(),
            self::EVERY_SIX_MONTHS => $date->addMonths(6),
            self::EVERY_YEAR => $date->addYear()
        };
    }
}
