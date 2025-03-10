<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Broadcasting\InteractsWithSockets;

class Message extends Model
{
    use InteractsWithSockets;
    protected $table = 'messages';

    protected $fillable = [
        'sender_id',
        'message',
    ];

    /**
     * Sender of the message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

}
