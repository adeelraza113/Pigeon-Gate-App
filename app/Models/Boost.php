<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boost extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'user_name', 'pigeon_id', 'payment_image', 'boost_start', 'boost_end'];

    protected $dates = ['boost_start', 'boost_end'];
    public function pigeon()
    {
        return $this->belongsTo(Pigeons::class, 'pigeon_id', 'id');
    }
}
