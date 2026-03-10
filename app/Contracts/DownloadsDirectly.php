<?php

namespace App\Contracts;

/**
 * Implemented by game handlers whose server binaries are downloaded
 * via HTTP rather than through SteamCMD.
 */
interface DownloadsDirectly
{
    /**
     * Full URL to download the server archive for the given branch.
     */
    public function getDownloadUrl(string $branch): string;

    /**
     * Number of leading directory components to strip when extracting the archive.
     *
     * For example, if the archive extracts to `factorio/bin/...`, return 1
     * so the contents are placed directly in the install directory.
     * Return 0 to extract as-is.
     */
    public function getArchiveStripComponents(): int;
}
