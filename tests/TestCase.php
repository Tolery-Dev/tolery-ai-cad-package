<?php

namespace Tolery\AiCad\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Tolery\AiCad\AiCadServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Tolery\\AiCad\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiCadServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        $migration = include __DIR__.'/../database/migrations/2024_12_05_00_create_chats_table.php';
        $migration = include __DIR__.'/../database/migrations/2024_12_05_01_create_chat_messages_table.php';
        $migration = include __DIR__.'/../database/migrations/2024_12_24_093516_create_subscription_products_table.php';
        $migration = include __DIR__.'/../database/migrations/2025_02_17_00_add_json_edge_path_to_chat_message_table.php';
        $migration->up();

    }
}
