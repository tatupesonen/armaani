<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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

    public function broadcastOn(): Channel
    {
        return new Channel('game-install.'.$this->gameInstallId);
    }
}
