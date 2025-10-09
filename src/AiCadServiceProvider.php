<?php

namespace Tolery\AiCad;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\AiCadCommand;
use Tolery\AiCad\Commands\LimitsAutoRenewal;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Livewire\ChatConfig;
use Tolery\AiCad\Models\ChatTeam;
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
            ->hasRoute('web')
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommands([
                AiCadCommand::class,
                LimitsAutoRenewal::class,
            ]);

        Cashier::useCustomerModel(ChatTeam::class);

        $this
            ->registerLivewireComponents()
            ->registerBladeDirective()
            ->scheduleCommandes();
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

    protected function scheduleCommandes(): static
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(LimitsAutoRenewal::class)->dailyAt('01:00');
        });

        return $this;
    }
}
