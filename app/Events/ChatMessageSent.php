<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $gameId,
        public int    $playerId,
        public string $playerName,
        public string $message,
        public float  $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('game.' . $this->gameId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'player_id'   => $this->playerId,
            'player_name' => $this->playerName,
            'message'     => $this->message,
            'timestamp'   => $this->timestamp,
        ];
    }
}
