<?php

namespace App\Contracts;

use App\Models\Server;

/**
 * Games that write their own serialized log files to disk.
 *
 * When a handler implements this interface, the system will tail the game's
 * native log files instead of capturing stdout/stderr (which can interleave
 * in multi-threaded game servers).
 */
interface WritesNativeLogs
{
    /**
     * Get the base directory where the game writes log files.
     *
     * Some games (e.g., Reforger) create timestamped subdirectories on each launch.
     * The tail command will discover the latest subdirectory automatically.
     */
    public function getNativeLogDirectory(Server $server): string;

    /**
     * Glob pattern to match log files within the discovered directory.
     * E.g., '*.log' to match all .log files.
     */
    public function getNativeLogFilePattern(): string;
}
