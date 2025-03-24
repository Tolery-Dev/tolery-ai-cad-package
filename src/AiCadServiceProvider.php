<?php

namespace Tolery\AiCad;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\AiCadCommand;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Livewire\ChatConfig;
use Tolery\AiCad\Models\ChatUser;

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
            ]);

        $this->registerLivewireComponents()
            ->registerBladeDirective();
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
            Blade::if('subscribed', function (): bool {
                try {
                    /** @var ChatUser $user */
                    $user = auth()->user();
                    return $user->team->subscribed();
                } catch (\Throwable $th) {
                    return false;
                }
            });
            Blade::if('hasLimit', function (): bool {
                try {
                    /** @var ChatUser $user */
                    $user = auth()->user();
                    return $user->team->limits()->exists();
                } catch (\Throwable $th) {
                    return false;
                }
            });
        });

        return $this;
    }
}
