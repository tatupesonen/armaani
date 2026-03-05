<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ModDownloadOutput implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $modId,
        public int $progressPct,
        public string $line,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('mod-download.'.$this->modId);
    }
}
