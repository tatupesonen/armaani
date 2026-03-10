<?php

namespace App\Contracts;

use App\Models\Server;

interface SupportsMissions
{
    /**
     * Create mission file symlinks in the game install directory.
     */
    public function symlinkMissions(Server $server): void;
}
