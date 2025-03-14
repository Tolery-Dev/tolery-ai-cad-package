<?php

namespace Tolery\AiCad\Enum;

enum MaterialFamily: string
{
    case STEEL = 'STEEL';
    case ALUMINUM = 'ALUMINUM';
    case STAINLESS = 'STAINLESS';

    public function label(): string
    {
        return match ($this) {
            self::STEEL => 'Acier',
            self::ALUMINUM => 'Alluminum',
            self::STAINLESS => 'Inox',
        };
    }
}
