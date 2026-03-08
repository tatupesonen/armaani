<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DayZSettings>
 */
class DayZSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory()->forDayZ(),
            'respawn_time' => 0,
            'time_acceleration' => 1.0,
            'night_time_acceleration' => 1.0,
            'force_same_build' => true,
            'third_person_view_enabled' => true,
            'crosshair_enabled' => true,
            'persistent' => true,
        ];
    }
}
