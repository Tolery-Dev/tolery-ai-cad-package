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
            ->hasAssets()
            ->hasMigrations([
                '2024_12_05_00_create_chats_table',
                '2024_12_05_01_create_chat_messages_table',
                '2024_12_24_093516_create_subscription_products_table',
                '2025_02_17_00_add_json_edge_path_to_chat_message_table',
                '2025_03_13_135738_add_name_and_mater_familly_to_chat',
            ])
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
