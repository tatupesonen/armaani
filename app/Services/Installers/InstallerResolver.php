<?php

namespace App\Services\Installers;

use App\Contracts\DownloadsDirectly;
use App\Contracts\GameHandler;
use App\Contracts\GameServerInstaller;
use App\Contracts\SteamGameHandler;

class InstallerResolver
{
    /**
     * Resolve the appropriate installer for the given game handler.
     *
     * Maps handler interfaces to installer classes:
     * - SteamGameHandler → SteamGameInstaller (SteamCMD downloads)
     * - DownloadsDirectly → HttpGameInstaller (HTTP downloads)
     */
    public function resolve(GameHandler $handler): GameServerInstaller
    {
        $installerClass = match (true) {
            $handler instanceof SteamGameHandler => SteamGameInstaller::class,
            $handler instanceof DownloadsDirectly => HttpGameInstaller::class,
            default => throw new \RuntimeException(
                'No installer registered for handler: '.get_class($handler).'. '
                .'The handler must implement SteamGameHandler or DownloadsDirectly.',
            ),
        };

        return app($installerClass);
    }
}
