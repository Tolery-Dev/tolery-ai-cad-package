<?php

namespace Tolery\AiCad;

use Illuminate\Database\Eloquent\Model;
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
use Tolery\AiCad\Models\Limit;
use Tolery\AiCad\Livewire\ChatConfig;

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
            ->hasViews()
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

        $this->registerLivewireComponents();

        Blade::if('limit', function (Model $model, string|Limit $name, ?string $plan = null): bool {
            try {
                return $model->hasEnoughLimit($name, $plan);
            } catch (\Throwable $th) {
                return false;
            }
        });

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
}
