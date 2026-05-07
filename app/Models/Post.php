<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    // Campos que permitimos llenar mediante Post::create()
    protected $fillable = [
        'user_id',
        'track_name',
        'artist_name',
        'album_name',
        'image_url',
        'comment',
    ];

    // Relación: Un post pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}