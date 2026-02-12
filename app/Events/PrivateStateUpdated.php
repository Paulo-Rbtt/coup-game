<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $state;

    public function __construct(
        public Game $game,
        public Player $player,
        public array $extra = [],
    ) {
        $this->state = array_merge($player->privateState(), $extra);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('player.' . $this->player->token),
        ];
    }

    public function broadcastAs(): string
    {
        return 'private.updated';
    }

    public function broadcastWith(): array
    {
        return ['state' => $this->state];
    }
}
