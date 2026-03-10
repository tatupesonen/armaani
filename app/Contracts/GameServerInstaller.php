<?php

namespace App\Contracts;

use App\Models\GameInstall;

/**
 * Handles the download and installation of a game server's files.
 *
 * Implementations encapsulate a specific installation strategy (e.g., SteamCMD, HTTP download)
 * and are resolved by the container via GameHandler::installerClass().
 */
interface GameServerInstaller
{
    /**
     * Download and install the game server files to the install directory.
     *
     * The callback receives a percentage (0–100) and a log line for progress streaming.
     * Returns the build ID if available (e.g., from SteamCMD appmanifest), or null.
     *
     * @param  callable(int $pct, string $line): void  $onOutput
     */
    public function install(GameInstall $install, GameHandler $handler, callable $onOutput): ?string;
}
