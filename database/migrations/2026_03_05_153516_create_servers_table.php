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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('port')->default(2302);
            $table->integer('query_port')->default(2303);
            $table->integer('max_players')->default(32);
            $table->string('password')->nullable();
            $table->string('admin_password')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('active_preset_id')->nullable()->constrained('mod_presets')->nullOnDelete();
            $table->integer('headless_client_count')->default(0);
            $table->text('additional_params')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
