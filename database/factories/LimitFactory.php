<?php

namespace Tolery\AiCad\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\Limit;
use Tolery\AiCad\Models\SubscriptionProduct;

/**
 * @extends Factory<Limit>
 */
class LimitFactory extends Factory
{
    protected $model = Limit::class;

    public function definition(): array
    {
        $startDate = CarbonImmutable::instance($this->faker->dateTimeBetween('-1 year', '+ 1 year'));
        $endDate = $startDate->addMonth();


        return [
            'subscription_product_id' => SubscriptionProduct::factory(),
            'team_id' => ChatTeam::factory(),
            'used_amount' => fake()->numberBetween(0, 100),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public function past(): LimitFactory
    {
        return $this->state(function (array $attributes) {
            $startDate = CarbonImmutable::instance($this->faker->dateTimeBetween('-1 year', '- 1 month'))->subDay();
            $endDate = $startDate->addMonth();

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }

    public function current(): LimitFactory
    {
        return $this->state(function (array $attributes) {
            $startDate = CarbonImmutable::instance($this->faker->dateTimeBetween('-1 month', 'now'))->addDay();
            $endDate = $startDate->addMonth();

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }
}
