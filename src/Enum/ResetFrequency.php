<?php

namespace Tolery\AiCad\Enum;

use Carbon\Carbon;

enum ResetFrequency: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

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
            self::MONTHLY => $date->addMonth(),
            self::YEARLY => $date->addYear(),
        };
    }

    public function stripInterval(): string
    {
        return match ($this) {
            self::MONTHLY => 'month',
            self::YEARLY => 'year',
        };
    }
}
