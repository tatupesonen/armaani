<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReforgerMod>
 */
class ReforgerModFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mod_id' => strtoupper(fake()->unique()->regexify('[0-9A-F]{16}')),
            'name' => fake()->words(3, true),
        ];
    }
}
