<?php

namespace Tolery\AiCad\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tolery\AiCad\Models\SubscriptionPrice;
use Tolery\AiCad\Models\SubscriptionProduct;

/**
 * @extends Factory<SubscriptionPrice>
 */
class SubscriptionPriceFactory extends Factory
{
    protected $model = SubscriptionPrice::class;

    public function definition(): array
    {
        return [
            'subscription_product_id' => SubscriptionProduct::factory(),
            'stripe_price_id' => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'amount' => fake()->numberBetween(1900, 24900),
            'currency' => 'eur',
            'interval' => fake()->randomElement(['month', 'year']),
            'active' => true,
            'archived_at' => null,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'month',
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'year',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
            'archived_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
