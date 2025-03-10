<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubScore extends Model
{
    protected $table = 'clubscore';

    protected $fillable = [
        'user_id',
        'club_score',
    ];
}
