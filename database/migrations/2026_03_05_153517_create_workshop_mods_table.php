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
        Schema::create('workshop_mods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workshop_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('installation_status')->default('queued');
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_mods');
    }
};
