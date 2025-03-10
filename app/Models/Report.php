<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;
    protected $table = 'reports';

    protected $fillable = [
        'pigeon_id',
        'user_id',
        'reason_for_report',
        'detail',
    ];

    public function pigeon()
    {
        return $this->belongsTo(Pigeons::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
