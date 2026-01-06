<?php

namespace Tolery\AiCad;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tolery\AiCad\Commands\DebugApiStream;
use Tolery\AiCad\Commands\LimitsAutoRenewal;
use Tolery\AiCad\Commands\MigratePrompts;
use Tolery\AiCad\Commands\SyncStripeProducts;
use Tolery\AiCad\Commands\TestApiConnection;
use Tolery\AiCad\Commands\TestStreamEndpoint;
use Tolery\AiCad\Commands\UpdateStripeMetadata;
use Tolery\AiCad\Livewire\Admin\ChatDetail;
use Tolery\AiCad\Livewire\Admin\ChatDownloadTable;
use Tolery\AiCad\Livewire\Admin\ChatTable;
use Tolery\AiCad\Livewire\Admin\Dashboard;
use Tolery\AiCad\Livewire\Admin\FilePurchaseTable;
use Tolery\AiCad\Livewire\Admin\PredefinedPromptForm;
use Tolery\AiCad\Livewire\Admin\PredefinedPromptTable;
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
                MigratePrompts::class,
            ]);

        Cashier::useCustomerModel(ChatTeam::class);

        $this
            ->registerLivewireComponents()
            ->registerAdminLivewireComponents()
            ->registerBladeDirective()
            ->scheduleCommandes();
    }

    public function boot(): void
    {
        parent::boot();

        // Register policies
        $this->registerPolicies();

        // Load admin routes conditionally
        if (config('ai-cad.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }

        // Publish admin views
        $this->publishes([
            __DIR__.'/../resources/views/admin' => resource_path('views/vendor/ai-cad/admin'),
        ], 'ai-cad-admin-views');
    }

    /**
     * Register authorization policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(\Tolery\AiCad\Models\Chat::class, \Tolery\AiCad\Policies\ChatPolicy::class);
        Gate::policy(\Tolery\AiCad\Models\FilePurchase::class, \Tolery\AiCad\Policies\FilePurchasePolicy::class);
        Gate::policy(\Tolery\AiCad\Models\ChatDownload::class, \Tolery\AiCad\Policies\ChatDownloadPolicy::class);
        Gate::policy(\Tolery\AiCad\Models\PredefinedPrompt::class, \Tolery\AiCad\Policies\PredefinedPromptPolicy::class);
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

    protected function registerAdminLivewireComponents(): self
    {
        if (! config('ai-cad.admin.enabled', true)) {
            return $this;
        }

        $this->callAfterResolving(BladeCompiler::class, function () {
            Livewire::component('ai-cad-admin-dashboard', Dashboard::class);
            Livewire::component('ai-cad-admin-chat-table', ChatTable::class);
            Livewire::component('ai-cad-admin-chat-detail', ChatDetail::class);
            Livewire::component('ai-cad-admin-file-purchase-table', FilePurchaseTable::class);
            Livewire::component('ai-cad-admin-chat-download-table', ChatDownloadTable::class);
            Livewire::component('ai-cad-admin-predefined-prompt-table', PredefinedPromptTable::class);
            Livewire::component('ai-cad-admin-predefined-prompt-form', PredefinedPromptForm::class);
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
            // Daily limit renewal
            $schedule->command(LimitsAutoRenewal::class)->dailyAt('01:00');
        });

        return $this;
    }
}
