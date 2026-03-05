<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DifficultySettings>
 */
class DifficultySettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'reduced_damage' => false,
            'group_indicators' => 2,
            'friendly_tags' => 2,
            'enemy_tags' => 0,
            'detected_mines' => 2,
            'commands' => 2,
            'waypoints' => 2,
            'tactical_ping' => 3,
            'weapon_info' => 2,
            'stance_indicator' => 2,
            'stamina_bar' => true,
            'weapon_crosshair' => true,
            'vision_aid' => false,
            'third_person_view' => 1,
            'camera_shake' => true,
            'score_table' => true,
            'death_messages' => true,
            'von_id' => true,
            'map_content' => true,
            'auto_report' => false,
            'ai_level_preset' => 1,
            'skill_ai' => 0.50,
            'precision_ai' => 0.50,
        ];
    }
}
