<?php

namespace Tolery\AiCad\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tolery\AiCad\Models\ChatTeam;

/**
 * @extends Factory<ChatTeam>
 */
class ChatTeamFactory extends Factory
{
    protected $model = ChatTeam::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
        ];
    }
}
