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
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        // Relación con el usuario que publica
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
        // Datos de la canción/álbum de Spotify
        $table->string('track_name');
        $table->string('artist_name');
        $table->string('album_name')->nullable();
        $table->string('image_url')->nullable();
        
        $table->text('comment')->nullable(); 
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
