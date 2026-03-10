<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factorio_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();

            // --- RCON ---
            $table->string('rcon_password')->nullable();

            // --- Server Settings ---
            $table->boolean('visibility_public')->default(true);
            $table->boolean('visibility_lan')->default(true);
            $table->boolean('require_user_verification')->default(true);
            $table->unsignedInteger('max_upload_kbps')->default(0);
            $table->unsignedInteger('max_heartbeats_per_second')->default(60);
            $table->boolean('ignore_player_limit_for_returning')->default(false);
            $table->string('allow_commands')->default('admins-only');
            $table->unsignedInteger('autosave_interval')->default(10);
            $table->unsignedInteger('autosave_slots')->default(5);
            $table->unsignedInteger('afk_autokick_interval')->default(0);
            $table->boolean('auto_pause')->default(true);
            $table->boolean('only_admins_can_pause')->default(true);
            $table->boolean('autosave_only_on_server')->default(true);
            $table->boolean('non_blocking_saving')->default(false);
            $table->text('tags')->nullable();

            // --- Map Generation: Resources ---
            $table->string('coal_frequency')->default('normal');
            $table->string('coal_size')->default('normal');
            $table->string('coal_richness')->default('normal');
            $table->string('copper_ore_frequency')->default('normal');
            $table->string('copper_ore_size')->default('normal');
            $table->string('copper_ore_richness')->default('normal');
            $table->string('crude_oil_frequency')->default('normal');
            $table->string('crude_oil_size')->default('normal');
            $table->string('crude_oil_richness')->default('normal');
            $table->string('enemy_base_frequency')->default('normal');
            $table->string('enemy_base_size')->default('normal');
            $table->string('enemy_base_richness')->default('normal');
            $table->string('iron_ore_frequency')->default('normal');
            $table->string('iron_ore_size')->default('normal');
            $table->string('iron_ore_richness')->default('normal');
            $table->string('stone_frequency')->default('normal');
            $table->string('stone_size')->default('normal');
            $table->string('stone_richness')->default('normal');
            $table->string('trees_frequency')->default('normal');
            $table->string('trees_size')->default('normal');
            $table->string('trees_richness')->default('normal');
            $table->string('uranium_ore_frequency')->default('normal');
            $table->string('uranium_ore_size')->default('normal');
            $table->string('uranium_ore_richness')->default('normal');

            // --- Map Generation: Terrain ---
            $table->unsignedInteger('map_width')->default(0);
            $table->unsignedInteger('map_height')->default(0);
            $table->string('starting_area')->default('normal');
            $table->boolean('peaceful_mode')->default(false);
            $table->string('map_seed')->nullable();
            $table->string('water')->default('normal');
            $table->string('terrain_segmentation')->default('normal');
            $table->decimal('cliff_elevation_0', 8, 2)->default(10.00);
            $table->decimal('cliff_elevation_interval', 8, 2)->default(40.00);
            $table->string('cliff_richness')->default('normal');

            // --- Map Settings: Gameplay ---
            $table->boolean('pollution_enabled')->default(true);
            $table->boolean('evolution_enabled')->default(true);
            $table->string('evolution_time_factor')->default('0.000004');
            $table->string('evolution_destroy_factor')->default('0.002');
            $table->string('evolution_pollution_factor')->default('0.0000009');
            $table->boolean('expansion_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factorio_settings');
    }
};
