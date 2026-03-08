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
        Schema::table('reforger_settings', function (Blueprint $table) {
            $table->boolean('cross_platform')->default(false)->after('max_fps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reforger_settings', function (Blueprint $table) {
            $table->dropColumn('cross_platform');
        });
    }
};
