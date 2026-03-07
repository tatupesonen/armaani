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
        Schema::create('mod_preset_reforger_mod', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_preset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reforger_mod_id')->constrained()->cascadeOnDelete();
            $table->unique(['mod_preset_id', 'reforger_mod_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_preset_reforger_mod');
    }
};
