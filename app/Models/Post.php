<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory; // Eliminamos "use BelongsToMany" de aquí, ya que no es un Trait.

    protected $fillable = [
        'user_id',
        'track_name',
        'artist_name',
        'album_name',
        'image_url',
        'comment',
    ];

    /**
     * Relación: El post pertenece a un creador.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Los usuarios que han dado "Like" a este post.
     */
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}