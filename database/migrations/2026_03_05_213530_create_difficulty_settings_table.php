<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('difficulty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();

            // Simulation
            $table->boolean('reduced_damage')->default(false);

            // Situational awareness (0 = never, 1 = limited distance, 2 = always)
            $table->tinyInteger('group_indicators')->default(2);
            $table->tinyInteger('friendly_tags')->default(2);
            $table->tinyInteger('enemy_tags')->default(0);
            $table->tinyInteger('detected_mines')->default(2);
            $table->tinyInteger('commands')->default(2);
            $table->tinyInteger('waypoints')->default(2);
            $table->tinyInteger('tactical_ping')->default(3); // 0=disabled, 1=3D, 2=map, 3=both

            // Personal awareness
            $table->tinyInteger('weapon_info')->default(2);
            $table->tinyInteger('stance_indicator')->default(2);
            $table->boolean('stamina_bar')->default(true);
            $table->boolean('weapon_crosshair')->default(true);
            $table->boolean('vision_aid')->default(false);

            // View
            $table->tinyInteger('third_person_view')->default(1); // 0=disabled, 1=enabled, 2=vehicles only
            $table->boolean('camera_shake')->default(true);

            // Multiplayer
            $table->boolean('score_table')->default(true);
            $table->boolean('death_messages')->default(true);
            $table->boolean('von_id')->default(true);

            // Misc
            $table->boolean('map_content')->default(true);
            $table->boolean('auto_report')->default(false);

            // AI level: 0=Low, 1=Normal, 2=High, 3=Custom
            $table->tinyInteger('ai_level_preset')->default(1);
            $table->decimal('skill_ai', 3, 2)->default(0.50);
            $table->decimal('precision_ai', 3, 2)->default(0.50);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('difficulty_settings');
    }
};
