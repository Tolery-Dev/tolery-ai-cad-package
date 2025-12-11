<?php

namespace Tolery\AiCad;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\CachePredefinedPromptsCommand;
use Tolery\AiCad\Commands\CleanupOldCachesCommand;
use Tolery\AiCad\Commands\DebugApiStream;
use Tolery\AiCad\Commands\LimitsAutoRenewal;
use Tolery\AiCad\Commands\SyncStripeProducts;
use Tolery\AiCad\Commands\TestApiConnection;
use Tolery\AiCad\Commands\TestStreamEndpoint;
use Tolery\AiCad\Commands\UpdateStripeMetadata;
use Tolery\AiCad\Jobs\RegeneratePredefinedCacheJob;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Livewire\ChatConfig;
use Tolery\AiCad\Livewire\ChatHistoryPanel;
use Tolery\AiCad\Livewire\StripePaymentModal;
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
            ->hasAssets()
            ->hasRoute('web')
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommands([
                LimitsAutoRenewal::class,
                TestApiConnection::class,
                DebugApiStream::class,
                TestStreamEndpoint::class,
                SyncStripeProducts::class,
                UpdateStripeMetadata::class,
                CachePredefinedPromptsCommand::class,
                CleanupOldCachesCommand::class,
            ]);

        Cashier::useCustomerModel(ChatTeam::class);

        $this
            ->registerLivewireComponents()
            ->registerBladeDirective()
            ->scheduleCommandes();
    }

    public function boot(): void
    {
        parent::boot();
    }

    protected function registerLivewireComponents(): self
    {
        $this->callAfterResolving(BladeCompiler::class, function () {
            Livewire::component('chatbot', Chatbot::class);
            Livewire::component('chat-config', ChatConfig::class);
            Livewire::component('stripe-payment-modal', StripePaymentModal::class);
            Livewire::component('chat-history-panel', ChatHistoryPanel::class);
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
            // Existing: Daily limit renewal
            $schedule->command(LimitsAutoRenewal::class)->dailyAt('01:00');

            // Cache: Weekly regeneration of predefined prompts (Sundays at 2 AM)
            $schedule->job(new RegeneratePredefinedCacheJob)->weekly()->sundays()->at('02:00');

            // Cache: Daily cleanup of old caches (3 AM)
            $schedule->command(CleanupOldCachesCommand::class)->dailyAt('03:00');
        });

        return $this;
    }
}
