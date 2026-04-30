<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    // Indicamos el nombre de la tabla si no es el plural en inglés
    protected $table = 'grupos';

    // Campos que permitimos llenar mediante código
    protected $fillable = ['name', 'creator_id'];

    // Relación: Un grupo pertenece a un creador (Usuario)
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // Relación: Un grupo tiene muchos usuarios (Muchos a Muchos)
    public function users()
    {
        return $this->belongsToMany(User::class, 'grupos_users', 'group_id', 'user_id')
                    ->withPivot('is_admin', 'joined_at');
    }
}