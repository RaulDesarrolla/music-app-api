<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
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
        'role'
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
        'last_activity_at' => 'datetime'
    ];

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

    public function follows(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
            ->withTimestamps();
    }


    public function spotifyToken()
    {
        return $this->hasOne(SpotifyToken::class);
    }
}