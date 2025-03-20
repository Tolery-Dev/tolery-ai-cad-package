<?php

namespace Tolery\AiCad;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\AiCadCommand;
use Tolery\AiCad\Commands\CreateLimit;
use Tolery\AiCad\Commands\DeleteLimit;
use Tolery\AiCad\Commands\ListLimits;
use Tolery\AiCad\Commands\ResetCache;
use Tolery\AiCad\Commands\ResetLimitUsages;
use Tolery\AiCad\Contracts\Limit as LimitContract;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Livewire\ChatConfig;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\Limit;

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
            ->hasConfigFile('ai-cad')
            ->hasViews('ai-cad')
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommands([
                AiCadCommand::class,
                CreateLimit::class,
                DeleteLimit::class,
                ListLimits::class,
                ResetLimitUsages::class,
                ResetCache::class,
            ]);

        $this->registerLivewireComponents()
            ->registerBladeDirective();

        $this->app->bind(LimitContract::class, Limit::class);
    }

    protected function registerLivewireComponents(): self
    {
        $this->callAfterResolving(BladeCompiler::class, function () {
            Livewire::component('chatbot', Chatbot::class);
            Livewire::component('chat-config', ChatConfig::class);
        });

        return $this;
    }

    protected function registerBladeDirective(): self
    {
        $this->callAfterResolving(BladeCompiler::class, function () {
            Blade::if('limit', function (ChatTeam $model, string|Limit $name, ?string $plan = null): bool {
                try {
                    return $model->hasEnoughLimit($name, $plan);
                } catch (\Throwable $th) {
                    return false;
                }
            });
        });

        return $this;
    }
}
