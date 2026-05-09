<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            // ID del usuario que sigue (Seguidor)
            $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
            // ID del usuario al que siguen (Seguido)
            $table->foreignId('followed_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Restricción para evitar que alguien se siga a sí mismo o duplique el seguimiento
            $table->unique(['follower_id', 'followed_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
