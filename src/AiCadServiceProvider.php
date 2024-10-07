<?php

namespace Tolery\AiCad;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\AiCadCommand;

class AiCadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('ai-cad')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_ai_cad_table')
            ->hasCommand(AiCadCommand::class);
    }
}
