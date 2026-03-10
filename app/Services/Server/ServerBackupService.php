<?php

namespace App\Services\Server;

use App\Contracts\SupportsBackups;
use App\GameManager;
use App\Models\Server;
use App\Models\ServerBackup;
use Illuminate\Support\Facades\Log;

class ServerBackupService
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    /**
     * Get the path to the profile backup file for a server.
     * Returns null if this game type has no profile backup concept.
     */
    public function getVarsFilePath(Server $server): ?string
    {
        $handler = $this->gameManager->for($server);

        if (! $handler instanceof SupportsBackups) {
            return null;
        }

        return $handler->getBackupFilePath($server);
    }

    /**
     * Create a backup from the server's current profile file.
     *
     * @return ServerBackup|null The backup, or null if no profile file exists or game doesn't support backups.
     */
    public function createFromServer(Server $server, ?string $name = null, bool $isAutomatic = false): ?ServerBackup
    {
        $varsPath = $this->getVarsFilePath($server);

        if ($varsPath === null) {
            return null;
        }

        if (! file_exists($varsPath)) {
            Log::info("{$server->logContext()} No profile backup file found, skipping backup");

            return null;
        }

        return $this->persistBackup($server, file_get_contents($varsPath), $name, $isAutomatic);
    }

    /**
     * Create a backup from uploaded file data.
     */
    public function createFromUpload(Server $server, string $data, ?string $name = null): ServerBackup
    {
        return $this->persistBackup($server, $data, $name, isAutomatic: false);
    }

    /**
     * Persist a backup record, log it, and prune old backups.
     */
    private function persistBackup(Server $server, string $data, ?string $name, bool $isAutomatic): ServerBackup
    {
        $fileSize = strlen($data);

        $backup = $server->backups()->create([
            'name' => $name,
            'file_size' => $fileSize,
            'is_automatic' => $isAutomatic,
            'data' => $data,
        ]);

        $type = $isAutomatic ? 'automatic' : 'manual';
        Log::info("{$server->logContext()} Created {$type} backup #{$backup->id} ({$fileSize} bytes)");

        $this->pruneOldBackups($server);

        return $backup;
    }

    /**
     * Restore a backup by writing its data to the server's profile backup path.
     */
    public function restore(ServerBackup $backup): void
    {
        $server = $backup->server;
        $varsPath = $this->getVarsFilePath($server);

        if ($varsPath === null) {
            Log::warning("{$server->logContext()} Game type does not support profile backups, cannot restore");

            return;
        }
        $varsDir = dirname($varsPath);

        if (! is_dir($varsDir)) {
            mkdir($varsDir, 0755, true);
        }

        file_put_contents($varsPath, $backup->data);

        Log::info("{$server->logContext()} Restored backup #{$backup->id}");
    }

    /**
     * Delete old backups when the configured limit is exceeded.
     */
    public function pruneOldBackups(Server $server): void
    {
        $maxBackups = (int) config('arma.max_backups_per_server');

        if ($maxBackups <= 0) {
            return;
        }

        $count = $server->backups()->count();

        if ($count <= $maxBackups) {
            return;
        }

        $toDelete = $server->backups()
            ->reorder('created_at', 'asc')
            ->limit($count - $maxBackups)
            ->get();

        foreach ($toDelete as $backup) {
            Log::info("{$server->logContext()} Pruning old backup #{$backup->id}");
            $backup->delete();
        }
    }
}
