<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;
    protected $table = 'reviews';

    protected $fillable = [
        'product_id',
        'shop_id',
        'ReasonForReview',
        'AddedBy',
    ];
    protected $casts = [
        'ReasonForReview' => 'array'
    ];
     public function product()
    {
        return $this->belongsTo(Products::class, 'product_id', 'id');
    }

    public $timestamps = true;
}
