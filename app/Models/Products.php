<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'shop_id',
        'category',
        'size',
        'price',
        'delivery',
        'description',
        'image',
    ];
    
    public function shop()
    {
        return $this->hasOne(Shops::class, 'id', 'shop_id');
    }
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'id');
    }
}
