<?php

namespace App\Contracts;

use App\Models\Server;

interface SupportsBackups
{
    /**
     * Get the path to the file that should be backed up (e.g., .vars.Arma3Profile).
     */
    public function getBackupFilePath(Server $server): string;

    /**
     * Get the filename to use when downloading a backup.
     */
    public function getBackupDownloadFilename(Server $server): string;
}
