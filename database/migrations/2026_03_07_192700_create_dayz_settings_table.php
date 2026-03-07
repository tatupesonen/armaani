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
        Schema::create('dayz_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->integer('respawn_time')->default(0);
            $table->decimal('time_acceleration', 5, 2)->default(1.0);
            $table->decimal('night_time_acceleration', 5, 2)->default(1.0);
            $table->boolean('force_same_build')->default(true);
            $table->boolean('third_person_view_enabled')->default(true);
            $table->boolean('crosshair_enabled')->default(true);
            $table->boolean('persistent')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dayz_settings');
    }
};
