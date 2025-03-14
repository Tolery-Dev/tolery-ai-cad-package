<?php

namespace Tolery\AiCad\Enum;

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
}
