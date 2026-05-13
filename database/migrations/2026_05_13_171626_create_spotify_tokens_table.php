<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spotify_tokens', function (Blueprint $table) {
            $table->id();
            // Relación con la tabla users
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Campos para los tokens de Spotify
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            
            // Metadatos del token
            $table->integer('expires_in'); // Duración en segundos
            $table->timestamp('expires_at'); // Momento exacto de expiración
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spotify_tokens');
    }
};