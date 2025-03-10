<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shops extends Model
{
    use HasFactory;
    protected $fillable = [
        'shop_name',
        'owner_name',
        'website',
        'category',
        'opening_hours',
        'return_policy',
        'shipping_policy',
        'image',
        'user_id',
    ];


    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

}
