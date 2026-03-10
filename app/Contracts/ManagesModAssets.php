<?php

namespace App\Contracts;

use App\Models\Server;

interface ManagesModAssets
{
    /**
     * Create mod symlinks in the game install directory for the server's active preset.
     */
    public function symlinkMods(Server $server): void;

    /**
     * Copy BiKey signature files from mod directories to the server's keys directory.
     */
    public function copyBiKeys(Server $server): void;
}
