<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Añadimos tus campos personalizados al fillable existente.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'spotify_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'theme',
        'notifications_enable',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'notifications_enable' => 'boolean',
        'expires_at' => 'datetime',
    ];

    // --- RELACIONES ---

    /**
     * Un usuario puede tener muchas publicaciones musicales.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Un usuario puede haber calificado muchas canciones.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Grupos a los que pertenece el usuario (Muchos a Muchos).
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupos_users', 'user_id', 'group_id')
                    ->withPivot('is_admin', 'joined_at');
    }
}