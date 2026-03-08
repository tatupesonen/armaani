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
        Schema::table('mod_presets', function (Blueprint $table) {
            $table->string('game_type')->default('arma3')->after('id');
            $table->dropUnique(['name']);
            $table->unique(['name', 'game_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_presets', function (Blueprint $table) {
            $table->dropUnique(['name', 'game_type']);
            $table->unique(['name']);
            $table->dropColumn('game_type');
        });
    }
};
