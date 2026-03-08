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
            $table->boolean('backend_log_enabled')->default(true)->after('third_person_view_enabled');
            $table->unsignedSmallInteger('max_fps')->default(60)->after('backend_log_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reforger_settings', function (Blueprint $table) {
            $table->dropColumn(['backend_log_enabled', 'max_fps']);
        });
    }
};
