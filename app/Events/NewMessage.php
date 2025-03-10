<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Message  $message
     * @return void
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'club_id' => $this->message->club_id,
            'message' => $this->message->message,
            'image' => $this->message->image ? Storage::disk('public')->url('uploads/' . $this->message->image) : null,
            'voice_messages' => $this->message->voice_messages ? Storage::disk('public')->url('uploads/' . $this->message->voice_messages) : null,
            'videos' => $this->message->videos ? Storage::disk('public')->url('uploads/' . $this->message->videos) : null,
            'created_at' => $this->message->created_at,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->message->sender_id)];
    }

    /**
     * Get the name of the event.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'new-message';
    }
}
