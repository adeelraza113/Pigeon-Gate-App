<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clubs extends Model
{
    use HasFactory;
    protected $fillable = [
        "club_name",
        "president_name",
        "club_image",
        "country_flag",
        'terms_&_conditions', 
        'joining_fee', 
    ];
    
    public function members()
    {
        return $this->hasMany(ClubMembers::class, 'club_id', 'id');
    }
}
