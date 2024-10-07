<?php

namespace Tolery\AiCad\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see package\src\AiCad
 */
class AiCad extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Tolery\AiCad\AiCad::class;
    }
}
