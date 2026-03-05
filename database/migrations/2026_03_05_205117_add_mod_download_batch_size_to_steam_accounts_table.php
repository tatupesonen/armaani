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
        Schema::table('steam_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('mod_download_batch_size')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steam_accounts', function (Blueprint $table) {
            $table->dropColumn('mod_download_batch_size');
        });
    }
};
