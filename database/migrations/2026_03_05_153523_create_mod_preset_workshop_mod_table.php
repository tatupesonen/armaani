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
        Schema::create('mod_preset_workshop_mod', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_preset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_mod_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mod_preset_id', 'workshop_mod_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_preset_workshop_mod');
    }
};
