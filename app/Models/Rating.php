<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['user_id', 'track_id', 'score', 'review'];

    // Relación: Un rating pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
