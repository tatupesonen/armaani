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
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('verify_signatures')->default(true);
            $table->boolean('allowed_file_patching')->default(false);
            $table->boolean('battle_eye')->default(true);
            $table->boolean('persistent')->default(false);
            $table->boolean('von_enabled')->default(true);
            $table->text('additional_server_options')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'verify_signatures',
                'allowed_file_patching',
                'battle_eye',
                'persistent',
                'von_enabled',
                'additional_server_options',
            ]);
        });
    }
};
