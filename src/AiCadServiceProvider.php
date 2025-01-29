<?php

namespace Tolery\AiCad;

use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\AiCadCommand;
use Tolery\AiCad\Livewire\Chatbot;

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
            ->hasAssets()
            ->hasMigrations([
                '2024_12_05_00_create_chats_table',
                '2024_12_05_01_create_chat_messages_table',
                '2024_12_24_093516_create_subscription_products_table',
            ])
            ->runsMigrations()
            ->hasCommand(AiCadCommand::class);

        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): self
    {
        Livewire::component('chatbot', Chatbot::class);

        return $this;
    }
}
