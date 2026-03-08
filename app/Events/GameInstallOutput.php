<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class GameInstallOutput implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $gameInstallId,
        public int $progressPct,
        public string $line,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('game-install.'.$this->gameInstallId);
    }
}
