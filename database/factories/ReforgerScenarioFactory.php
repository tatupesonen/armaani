<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReforgerScenario>
 */
class ReforgerScenarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory()->forReforger(),
            'value' => '{'.strtoupper($this->faker->regexify('[0-9A-F]{16}')).'}Missions/'.$this->faker->word().'.conf',
            'name' => $this->faker->words(2, true),
            'is_official' => $this->faker->boolean(),
        ];
    }

    public function official(): static
    {
        return $this->state(fn () => ['is_official' => true]);
    }

    public function workshop(): static
    {
        return $this->state(fn () => ['is_official' => false]);
    }
}
