<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            
            // Relación con el post reportado (si el post se elimina, el reporte también)
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
            
            // Relación con el usuario que denuncia (opcional, por si quieres trackearlo)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Motivo del reporte (opcional, ej: "Spam", "Acoso")
            $table->string('reason')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};