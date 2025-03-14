<?php

namespace Tolery\AiCad;

use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\AiCadCommand;
use Tolery\AiCad\Livewire\Chatbot;
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
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommand(AiCadCommand::class);

        $this->registerLivewireComponents();
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
