<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Personal -> kirim ke channel penerima.
     * Grup     -> fan-out ke channel SEMUA anggota grup.
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if (!empty($this->message->group_id)) {
            $memberIds = DB::table('llxjp_chat_group_members')
                ->where('group_id', $this->message->group_id)
                ->pluck('user_id');

            foreach ($memberIds as $uid) {
                $channels[] = new Channel('chat.user.' . $uid);
            }
        } else {
            $channels[] = new Channel('chat.user.' . $this->message->receiver_id);
        }

        return $channels;
    }

    /** Tanpa ini, nama event = "App\Events\MessageSent" */
    public function broadcastAs(): string
    {
        return 'new-message';
    }

    /** Android sudah membaca key "message", jadi dibungkus di sini */
    public function broadcastWith(): array
    {
        return ['message' => (array) $this->message];
    }
}