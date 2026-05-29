<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('grupos_users', function (Blueprint $table) {
            $table->foreignId('grupo_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->primary(['grupo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_users');
    }
};
