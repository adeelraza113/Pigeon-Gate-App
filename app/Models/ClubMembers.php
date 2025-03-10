<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubMembers extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'club_id',
        'role',
        'request_approved',
        'payment_method',
        'payment_image'
    ];
   
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
