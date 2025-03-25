<?php

namespace Tolery\AiCad\Enum;


use Carbon\Carbon;
use Carbon\CarbonImmutable;

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

    public function addTime(Carbon|CarbonImmutable $date): Carbon|CarbonImmutable
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
