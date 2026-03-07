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
        Schema::table('workshop_mods', function (Blueprint $table) {
            $table->string('game_type')->default('arma3')->after('id');
            $table->dropUnique(['workshop_id']);
            $table->unique(['workshop_id', 'game_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_mods', function (Blueprint $table) {
            $table->dropUnique(['workshop_id', 'game_type']);
            $table->unique(['workshop_id']);
            $table->dropColumn('game_type');
        });
    }
};
