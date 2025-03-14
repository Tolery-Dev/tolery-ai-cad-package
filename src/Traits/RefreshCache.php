<?php

namespace Tolery\AiCad\Traits;

use Tolery\AiCad\LimitManager;

trait RefreshCache
{
    protected static function bootRefreshCache(): void
    {
        static::saving(function () {
            app(LimitManager::class)->flushCache();
        });

        static::deleted(function () {
            app(LimitManager::class)->flushCache();
        });
    }
}
