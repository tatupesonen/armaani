<?php

namespace App\Concerns;

use App\Models\Server;

/**
 * Default implementations for the DetectsServerState interface.
 *
 * Derives supportsAutoRestart from getCrashDetectionStrings() and
 * gates shouldAutoRestart on both the capability and the server flag.
 */
trait DetectsServerStateBehavior
{
    public function supportsAutoRestart(): bool
    {
        return count($this->getCrashDetectionStrings()) > 0;
    }

    public function shouldAutoRestart(Server $server): bool
    {
        return $this->supportsAutoRestart() && (bool) $server->auto_restart;
    }
}
