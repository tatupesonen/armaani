<?php

namespace Database\Factories;

use App\Enums\GameType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModPreset>
 */
class ModPresetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_type' => GameType::Arma3,
            'name' => fake()->unique()->words(2, true).' preset',
        ];
    }

    public function reforger(): static
    {
        return $this->state(fn (): array => [
            'game_type' => GameType::ArmaReforger,
        ]);
    }

    public function dayz(): static
    {
        return $this->state(fn (): array => [
            'game_type' => GameType::DayZ,
        ]);
    }
}
