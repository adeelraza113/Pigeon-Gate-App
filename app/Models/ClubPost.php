<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubPost extends Model
{
    use HasFactory;
    protected $fillable = [
        'club_id',
        'description',
        'image',
        'pigeon_name',  
        'champion_year', 
    ];

    // Relationship with Club model
    public function club()
    {
        return $this->belongsTo(Clubs::class);
    }
}
