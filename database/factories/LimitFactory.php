<?php

namespace Tolery\AiCad\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\Limit;
use Tolery\AiCad\Models\SubscriptionProduct;

class LimitFactory extends Factory
{

    protected $model = Limit::class;
    /**
     */
    public function definition(): array
    {
        $startDate = CarbonImmutable::instance( $this->faker->dateTimeBetween('-1 year', '+ 1 year'));
        $endDate = $startDate->addMonth();

        $team = Mockery::mock(ChatTeam::class);
        $team->shouldReceive('getKey')->andReturn(1);

        return [
            'subscription_product_id' => SubscriptionProduct::factory(),
            'team_id' => $team->getKey(),
            'used_amount' => fake()->numberBetween(0, 100),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public function past(): LimitFactory
    {
        return $this->state(function (array $attributes) {
            $startDate = CarbonImmutable::instance( $this->faker->dateTimeBetween('-1 year','- 1 month'))->subDay();
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
            $startDate = CarbonImmutable::instance( $this->faker->dateTimeBetween('-1 month','now'))->addDay();
            $endDate = $startDate->addMonth();

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }
}
