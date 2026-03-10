<?php

namespace App\Contracts;

/**
 * Implemented by game handlers that use Steam for server installation and mod downloads.
 */
interface SteamGameHandler
{
    /**
     * Steam App ID for the dedicated server binary (used by SteamCMD +app_update).
     */
    public function serverAppId(): int;

    /**
     * Steam Game ID for workshop mod downloads (used by SteamCMD +workshop_download_item).
     */
    public function gameId(): int;

    /**
     * Steam consumer App ID used by the Steam Web API to identify this game's workshop.
     * Used for auto-detecting which game a workshop mod belongs to.
     */
    public function consumerAppId(): int;
}
