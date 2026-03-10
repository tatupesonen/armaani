<?php

namespace Database\Factories;

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
            'game_type' => 'arma3',
            'name' => fake()->unique()->words(2, true).' preset',
        ];
    }

    public function forGame(string $gameType): static
    {
        return $this->state(fn (): array => [
            'game_type' => $gameType,
        ]);
    }

    public function reforger(): static
    {
        return $this->forGame('reforger');
    }

    public function dayz(): static
    {
        return $this->forGame('dayz');
    }
}
