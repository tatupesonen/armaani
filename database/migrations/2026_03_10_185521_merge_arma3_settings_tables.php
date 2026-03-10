<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('arma3_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();

            // --- Difficulty settings (from difficulty_settings table) ---

            // Simulation
            $table->boolean('reduced_damage')->default(false);

            // Situational awareness (0 = never, 1 = limited distance, 2 = always)
            $table->tinyInteger('group_indicators')->default(2);
            $table->tinyInteger('friendly_tags')->default(2);
            $table->tinyInteger('enemy_tags')->default(0);
            $table->tinyInteger('detected_mines')->default(2);
            $table->tinyInteger('commands')->default(2);
            $table->tinyInteger('waypoints')->default(2);
            $table->tinyInteger('tactical_ping')->default(3);

            // Personal awareness
            $table->tinyInteger('weapon_info')->default(2);
            $table->tinyInteger('stance_indicator')->default(2);
            $table->boolean('stamina_bar')->default(true);
            $table->boolean('weapon_crosshair')->default(true);
            $table->boolean('vision_aid')->default(false);

            // View
            $table->tinyInteger('third_person_view')->default(1);
            $table->boolean('camera_shake')->default(true);

            // Multiplayer
            $table->boolean('score_table')->default(true);
            $table->boolean('death_messages')->default(true);
            $table->boolean('von_id')->default(true);

            // Misc
            $table->boolean('map_content')->default(true);
            $table->boolean('auto_report')->default(false);

            // AI level
            $table->tinyInteger('ai_level_preset')->default(1);
            $table->decimal('skill_ai', 3, 2)->default(0.50);
            $table->decimal('precision_ai', 3, 2)->default(0.50);

            // --- Network settings (from network_settings table) ---

            // Messaging
            $table->unsignedInteger('max_msg_send')->default(128);
            $table->unsignedInteger('max_size_guaranteed')->default(512);
            $table->unsignedInteger('max_size_nonguaranteed')->default(256);

            // Bandwidth
            $table->unsignedBigInteger('min_bandwidth')->default(131072);
            $table->unsignedBigInteger('max_bandwidth')->default(10000000000);

            // Error thresholds
            $table->decimal('min_error_to_send', 8, 4)->default(0.001);
            $table->decimal('min_error_to_send_near', 8, 4)->default(0.01);

            // Sockets
            $table->unsignedInteger('max_packet_size')->default(1400);

            // Custom content
            $table->unsignedInteger('max_custom_file_size')->default(0);

            // Terrain & view
            $table->decimal('terrain_grid', 8, 4)->default(25.0);
            $table->unsignedInteger('view_distance')->default(0);

            $table->timestamps();
        });

        // Migrate existing data by joining both tables on server_id.
        // Use LEFT JOINs so we don't lose data if one table has a row but the other doesn't.
        DB::statement('
            INSERT INTO arma3_settings (
                server_id,
                reduced_damage, group_indicators, friendly_tags, enemy_tags, detected_mines,
                commands, waypoints, tactical_ping, weapon_info, stance_indicator,
                stamina_bar, weapon_crosshair, vision_aid, third_person_view, camera_shake,
                score_table, death_messages, von_id, map_content, auto_report,
                ai_level_preset, skill_ai, precision_ai,
                max_msg_send, max_size_guaranteed, max_size_nonguaranteed,
                min_bandwidth, max_bandwidth, min_error_to_send, min_error_to_send_near,
                max_packet_size, max_custom_file_size, terrain_grid, view_distance,
                created_at, updated_at
            )
            SELECT
                COALESCE(d.server_id, n.server_id) as server_id,
                COALESCE(d.reduced_damage, 0),
                COALESCE(d.group_indicators, 2),
                COALESCE(d.friendly_tags, 2),
                COALESCE(d.enemy_tags, 0),
                COALESCE(d.detected_mines, 2),
                COALESCE(d.commands, 2),
                COALESCE(d.waypoints, 2),
                COALESCE(d.tactical_ping, 3),
                COALESCE(d.weapon_info, 2),
                COALESCE(d.stance_indicator, 2),
                COALESCE(d.stamina_bar, 1),
                COALESCE(d.weapon_crosshair, 1),
                COALESCE(d.vision_aid, 0),
                COALESCE(d.third_person_view, 1),
                COALESCE(d.camera_shake, 1),
                COALESCE(d.score_table, 1),
                COALESCE(d.death_messages, 1),
                COALESCE(d.von_id, 1),
                COALESCE(d.map_content, 1),
                COALESCE(d.auto_report, 0),
                COALESCE(d.ai_level_preset, 1),
                COALESCE(d.skill_ai, 0.50),
                COALESCE(d.precision_ai, 0.50),
                COALESCE(n.max_msg_send, 128),
                COALESCE(n.max_size_guaranteed, 512),
                COALESCE(n.max_size_nonguaranteed, 256),
                COALESCE(n.min_bandwidth, 131072),
                COALESCE(n.max_bandwidth, 10000000000),
                COALESCE(n.min_error_to_send, 0.001),
                COALESCE(n.min_error_to_send_near, 0.01),
                COALESCE(n.max_packet_size, 1400),
                COALESCE(n.max_custom_file_size, 0),
                COALESCE(n.terrain_grid, 25.0),
                COALESCE(n.view_distance, 0),
                COALESCE(d.created_at, n.created_at, CURRENT_TIMESTAMP),
                COALESCE(d.updated_at, n.updated_at, CURRENT_TIMESTAMP)
            FROM difficulty_settings d
            FULL OUTER JOIN network_settings n ON d.server_id = n.server_id
            WHERE COALESCE(d.server_id, n.server_id) IS NOT NULL
        ');

        Schema::dropIfExists('difficulty_settings');
        Schema::dropIfExists('network_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate original tables
        Schema::create('difficulty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('reduced_damage')->default(false);
            $table->tinyInteger('group_indicators')->default(2);
            $table->tinyInteger('friendly_tags')->default(2);
            $table->tinyInteger('enemy_tags')->default(0);
            $table->tinyInteger('detected_mines')->default(2);
            $table->tinyInteger('commands')->default(2);
            $table->tinyInteger('waypoints')->default(2);
            $table->tinyInteger('tactical_ping')->default(3);
            $table->tinyInteger('weapon_info')->default(2);
            $table->tinyInteger('stance_indicator')->default(2);
            $table->boolean('stamina_bar')->default(true);
            $table->boolean('weapon_crosshair')->default(true);
            $table->boolean('vision_aid')->default(false);
            $table->tinyInteger('third_person_view')->default(1);
            $table->boolean('camera_shake')->default(true);
            $table->boolean('score_table')->default(true);
            $table->boolean('death_messages')->default(true);
            $table->boolean('von_id')->default(true);
            $table->boolean('map_content')->default(true);
            $table->boolean('auto_report')->default(false);
            $table->tinyInteger('ai_level_preset')->default(1);
            $table->decimal('skill_ai', 3, 2)->default(0.50);
            $table->decimal('precision_ai', 3, 2)->default(0.50);
            $table->timestamps();
        });

        Schema::create('network_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_msg_send')->default(128);
            $table->unsignedInteger('max_size_guaranteed')->default(512);
            $table->unsignedInteger('max_size_nonguaranteed')->default(256);
            $table->unsignedBigInteger('min_bandwidth')->default(131072);
            $table->unsignedBigInteger('max_bandwidth')->default(10000000000);
            $table->decimal('min_error_to_send', 8, 4)->default(0.001);
            $table->decimal('min_error_to_send_near', 8, 4)->default(0.01);
            $table->unsignedInteger('max_packet_size')->default(1400);
            $table->unsignedInteger('max_custom_file_size')->default(0);
            $table->decimal('terrain_grid', 8, 4)->default(25.0);
            $table->unsignedInteger('view_distance')->default(0);
            $table->timestamps();
        });

        // Migrate data back
        DB::statement('
            INSERT INTO difficulty_settings (
                server_id, reduced_damage, group_indicators, friendly_tags, enemy_tags,
                detected_mines, commands, waypoints, tactical_ping, weapon_info,
                stance_indicator, stamina_bar, weapon_crosshair, vision_aid, third_person_view,
                camera_shake, score_table, death_messages, von_id, map_content, auto_report,
                ai_level_preset, skill_ai, precision_ai, created_at, updated_at
            )
            SELECT
                server_id, reduced_damage, group_indicators, friendly_tags, enemy_tags,
                detected_mines, commands, waypoints, tactical_ping, weapon_info,
                stance_indicator, stamina_bar, weapon_crosshair, vision_aid, third_person_view,
                camera_shake, score_table, death_messages, von_id, map_content, auto_report,
                ai_level_preset, skill_ai, precision_ai, created_at, updated_at
            FROM arma3_settings
        ');

        DB::statement('
            INSERT INTO network_settings (
                server_id, max_msg_send, max_size_guaranteed, max_size_nonguaranteed,
                min_bandwidth, max_bandwidth, min_error_to_send, min_error_to_send_near,
                max_packet_size, max_custom_file_size, terrain_grid, view_distance,
                created_at, updated_at
            )
            SELECT
                server_id, max_msg_send, max_size_guaranteed, max_size_nonguaranteed,
                min_bandwidth, max_bandwidth, min_error_to_send, min_error_to_send_near,
                max_packet_size, max_custom_file_size, terrain_grid, view_distance,
                created_at, updated_at
            FROM arma3_settings
        ');

        Schema::dropIfExists('arma3_settings');
    }
};
