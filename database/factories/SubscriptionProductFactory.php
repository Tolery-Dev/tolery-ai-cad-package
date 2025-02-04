<?php

namespace Tolery\AiCad\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Tolery\AiCad\Models\SubscriptionProduct;

/**
 * @extends Factory<SubscriptionProduct>
 */
class SubscriptionProductFactory extends Factory
{
    protected $model = SubscriptionProduct::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'active' => $this->faker->boolean(),
            'price' => $this->faker->numberBetween(49, 9999),
            'description' => $this->faker->text(),
            'stripe_id' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
