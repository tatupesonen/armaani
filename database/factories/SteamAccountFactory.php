<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SteamAccount>
 */
class SteamAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'password' => fake()->password(),
            'auth_token' => null,
        ];
    }

    public function withAuthToken(): static
    {
        return $this->state(fn (): array => [
            'auth_token' => fake()->regexify('[A-Z0-9]{5}'),
        ]);
    }
}
