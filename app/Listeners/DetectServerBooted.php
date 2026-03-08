<?php

namespace App\Listeners;

use App\Enums\ServerStatus;
use App\Events\ServerLogOutput;
use App\Events\ServerStatusChanged;
use App\GameManager;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class DetectServerBooted
{
    /**
     * Handle the event.
     *
     * When a server log line contains the game-specific boot detection string
     * and the server is still in the Booting state, transition it to Running.
     */
    public function handle(ServerLogOutput $event): void
    {
        $server = Server::query()->find($event->serverId);

        if (! $server) {
            return;
        }

        $handler = app(GameManager::class)->for($server);
        $bootString = $handler->getBootDetectionString();

        if ($bootString === null || ! str_contains($event->line, $bootString)) {
            return;
        }

        $updated = Server::query()
            ->where('id', $event->serverId)
            ->where('status', ServerStatus::Booting)
            ->update(['status' => ServerStatus::Running]);

        if ($updated) {
            $serverName = Server::query()->where('id', $event->serverId)->value('name') ?? 'Server';
            Log::info("[Server:{$event->serverId}] Connected to Steam servers — status changed from Booting to Running");
            ServerStatusChanged::dispatch($event->serverId, ServerStatus::Running->value, $serverName);
        }
    }
}
