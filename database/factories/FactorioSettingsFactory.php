<?php

namespace Database\Factories;

use App\Models\FactorioSettings;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FactorioSettings>
 */
class FactorioSettingsFactory extends Factory
{
    protected $model = FactorioSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory()->forFactorio(),

            // RCON
            'rcon_password' => null,

            // Server settings
            'visibility_public' => true,
            'visibility_lan' => true,
            'require_user_verification' => true,
            'max_upload_kbps' => 0,
            'max_heartbeats_per_second' => 60,
            'ignore_player_limit_for_returning' => false,
            'allow_commands' => 'admins-only',
            'autosave_interval' => 10,
            'autosave_slots' => 5,
            'afk_autokick_interval' => 0,
            'auto_pause' => true,
            'only_admins_can_pause' => true,
            'autosave_only_on_server' => true,
            'non_blocking_saving' => false,
            'tags' => null,

            // Map generation: resources (all default to 'normal')
            'coal_frequency' => 'normal',
            'coal_size' => 'normal',
            'coal_richness' => 'normal',
            'copper_ore_frequency' => 'normal',
            'copper_ore_size' => 'normal',
            'copper_ore_richness' => 'normal',
            'crude_oil_frequency' => 'normal',
            'crude_oil_size' => 'normal',
            'crude_oil_richness' => 'normal',
            'enemy_base_frequency' => 'normal',
            'enemy_base_size' => 'normal',
            'enemy_base_richness' => 'normal',
            'iron_ore_frequency' => 'normal',
            'iron_ore_size' => 'normal',
            'iron_ore_richness' => 'normal',
            'stone_frequency' => 'normal',
            'stone_size' => 'normal',
            'stone_richness' => 'normal',
            'trees_frequency' => 'normal',
            'trees_size' => 'normal',
            'trees_richness' => 'normal',
            'uranium_ore_frequency' => 'normal',
            'uranium_ore_size' => 'normal',
            'uranium_ore_richness' => 'normal',

            // Map generation: terrain
            'map_width' => 0,
            'map_height' => 0,
            'starting_area' => 'normal',
            'peaceful_mode' => false,
            'map_seed' => null,
            'water' => 'normal',
            'terrain_segmentation' => 'normal',
            'cliff_elevation_0' => 10.00,
            'cliff_elevation_interval' => 40.00,
            'cliff_richness' => 'normal',

            // Map settings: gameplay
            'pollution_enabled' => true,
            'evolution_enabled' => true,
            'evolution_time_factor' => '0.000004',
            'evolution_destroy_factor' => '0.002',
            'evolution_pollution_factor' => '0.0000009',
            'expansion_enabled' => true,
        ];
    }
}
