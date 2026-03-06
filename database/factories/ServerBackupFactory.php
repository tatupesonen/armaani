<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerBackup>
 */
class ServerBackupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $data = 'version=148;'."\n".'blood=1;'."\n";

        return [
            'server_id' => Server::factory(),
            'name' => fake()->optional()->words(3, true),
            'file_size' => strlen($data),
            'is_automatic' => false,
            'data' => $data,
        ];
    }

    public function automatic(): static
    {
        return $this->state(fn (): array => [
            'is_automatic' => true,
            'name' => 'Auto-backup before start',
        ]);
    }
}
