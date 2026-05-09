<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// 1. Importamos el trait de Sanctum para usar tokens
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    // 2. Añadimos HasApiTokens aquí
    use HasApiTokens, HasFactory, Notifiable;

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

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupos_users', 'user_id', 'group_id')
                    ->withPivot('is_admin', 'joined_at');
    }

    /**
     * 3. Relación: Usuarios a los que SIGUE este usuario.
     */
    public function follows(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
                    ->withTimestamps();
    }

    /**
     * 4. Relación: Usuarios que SIGUEN a este usuario.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
                    ->withTimestamps();
    }
}