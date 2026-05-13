<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpotifyToken extends Model
{
    use HasFactory;

    // Campos que permitimos guardar mediante código
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_in',
        'expires_at',
    ];

    // Indica que los campos de fecha deben ser tratados como objetos Carbon (fechas)
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Relación inversa: Un token pertenece a un usuario.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}