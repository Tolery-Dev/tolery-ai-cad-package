<?php

namespace Tolery\AiCad\Commands;

use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class UpdateStripeMetadata extends Command
{
    public $signature = 'ai-cad:update-stripe-metadata';

    public $description = 'Update Stripe products metadata (files_allowed)';

    public function handle(): int
    {
        $this->info('🔄 Updating Stripe products metadata...');
        $this->newLine();

        $productsConfig = [
            'prod_TNEkY1Wyo2YZ53' => ['name' => 'One shot', 'files_allowed' => '1'],
            'prod_TNEgnQ0Yq8Bmhk' => ['name' => 'Basic Plan', 'files_allowed' => '10'],
            'prod_TNEgDcNrSjbTSI' => ['name' => 'Pro', 'files_allowed' => '100'],
            'prod_TNEhhxy2YtKUaU' => ['name' => 'Business', 'files_allowed' => '200'],
            'prod_TNEhfBlXhPhLjk' => ['name' => 'Galaxie', 'files_allowed' => '-1'],
        ];

        $stripe = Cashier::stripe();
        $updated = 0;
        $errors = [];

        foreach ($productsConfig as $productId => $config) {
            try {
                $stripe->products->update($productId, [
                    'metadata' => [
                        'files_allowed' => $config['files_allowed'],
                    ],
                ]);

                $this->info("✅ Updated {$config['name']}: {$config['files_allowed']} files");
                $updated++;
            } catch (ApiErrorException $e) {
                $error = "❌ {$config['name']}: {$e->getMessage()}";
                $this->error($error);
                $errors[] = $error;
            }
        }

        $this->newLine();
        $this->info("✨ Updated {$updated} products");

        if (! empty($errors)) {
            $this->newLine();
            $this->warn('⚠️  Errors encountered:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
