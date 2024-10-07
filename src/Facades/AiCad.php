<?php

namespace Tolery\AiCad\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see AiCad
 */
class AiCad extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiCad::class;
    }
}
