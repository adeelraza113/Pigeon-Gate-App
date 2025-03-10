<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Articles extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'author_name',
        'publication_date',
        'image',
        'content',
        'views',
        'likes',
        'comments_count',
        'user_id'
    ];
    
    public function comments()
    {
        return $this->hasMany(Comment::class, 'article_id');
    }
}
