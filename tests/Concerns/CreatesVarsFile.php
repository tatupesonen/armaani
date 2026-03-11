<?php

namespace Tests\Concerns;

use App\Models\Server;
use App\Services\Server\ServerBackupService;

trait CreatesVarsFile
{
    protected function createVarsFile(Server $server, string $content): string
    {
        $service = app(ServerBackupService::class);
        $varsPath = $service->getVarsFilePath($server);
        $varsDir = dirname($varsPath);

        if (! is_dir($varsDir)) {
            mkdir($varsDir, 0755, true);
        }

        file_put_contents($varsPath, $content);

        return $varsPath;
    }
}
