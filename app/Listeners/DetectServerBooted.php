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
     * Detects game-specific log strings to transition server status:
     * - Boot detection string: Booting/DownloadingMods → Running
     * - Mod download started string: Booting → DownloadingMods
     * - Mod download finished string: DownloadingMods → Booting
     */
    public function handle(ServerLogOutput $event): void
    {
        $server = Server::query()->find($event->serverId);

        if (! $server) {
            return;
        }

        $handler = app(GameManager::class)->for($server);

        // Check for mod download started (Booting → DownloadingMods)
        $modDownloadStarted = $handler->getModDownloadStartedString();
        if ($modDownloadStarted !== null && str_contains($event->line, $modDownloadStarted)) {
            $updated = Server::query()
                ->where('id', $event->serverId)
                ->where('status', ServerStatus::Booting)
                ->update(['status' => ServerStatus::DownloadingMods]);

            if ($updated) {
                $serverName = Server::query()->where('id', $event->serverId)->value('name') ?? 'Server';
                Log::info("[Server:{$event->serverId}] Mod download started — status changed from Booting to DownloadingMods");
                ServerStatusChanged::dispatch($event->serverId, ServerStatus::DownloadingMods->value, $serverName);
            }

            return;
        }

        // Check for mod download finished (DownloadingMods → Booting)
        $modDownloadFinished = $handler->getModDownloadFinishedString();
        if ($modDownloadFinished !== null && str_contains($event->line, $modDownloadFinished)) {
            $updated = Server::query()
                ->where('id', $event->serverId)
                ->where('status', ServerStatus::DownloadingMods)
                ->update(['status' => ServerStatus::Booting]);

            if ($updated) {
                $serverName = Server::query()->where('id', $event->serverId)->value('name') ?? 'Server';
                Log::info("[Server:{$event->serverId}] Mod download finished — status changed from DownloadingMods to Booting");
                ServerStatusChanged::dispatch($event->serverId, ServerStatus::Booting->value, $serverName);
            }

            return;
        }

        // Check for boot detection (Booting/DownloadingMods → Running)
        $bootString = $handler->getBootDetectionString();
        if ($bootString === null || ! str_contains($event->line, $bootString)) {
            return;
        }

        $updated = Server::query()
            ->where('id', $event->serverId)
            ->whereIn('status', [ServerStatus::Booting, ServerStatus::DownloadingMods])
            ->update(['status' => ServerStatus::Running]);

        if ($updated) {
            $serverName = Server::query()->where('id', $event->serverId)->value('name') ?? 'Server';
            Log::info("[Server:{$event->serverId}] Boot detected — status changed to Running");
            ServerStatusChanged::dispatch($event->serverId, ServerStatus::Running->value, $serverName);
        }
    }
}
