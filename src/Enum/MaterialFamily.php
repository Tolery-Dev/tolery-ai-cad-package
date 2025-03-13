<?php

namespace Tolery\AiCad\Enum;

enum MaterialFamily : string
{

    case STEEL = 'steel';
    case ALUMINUM = 'aluminum';
    case INOX = 'inox';


    public function label(): string
    {
        return match ($this) {
            self::STEEL => 'Acier',
            self::ALUMINUM => 'Alluminum',
            self::INOX => 'Inox',
        };
    }
}
