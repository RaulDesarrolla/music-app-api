<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar de forma masiva.
     * 
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'track_id',
        'content',
    ];

    /**
     * Obtener el usuario que creó la publicación.
     * 
     * Relación: Muchos posts pertenecen a un Usuario (N:1)
     */
    public function user(): BelongsTo
    {
        // Laravel asume que la clave foránea es user_id
        return $this->belongsTo(User::class);
    }
}
