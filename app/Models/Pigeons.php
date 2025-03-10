<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pigeons extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'price',
        'gender', 
        'color', 
        'ring_number',
        'weight',
        'vaccination', 
        'location', 
        'description',
        'images',
        'user_id'
    ];
    
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    
      public function reports()
{
    return $this->hasMany(Report::class);
}
    
}
