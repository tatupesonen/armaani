<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\SteamCmdService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class InstallServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 7200;

    public function __construct(public Server $server) {}

    public function handle(SteamCmdService $steamCmd): void
    {
        $installDir = $this->server->getInstallationPath();

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        Log::info("Starting server installation for '{$this->server->name}' to {$installDir}");

        $result = $steamCmd->installServer($installDir);

        if ($result->successful()) {
            Log::info("Server '{$this->server->name}' installed/updated successfully");
        } else {
            Log::error("Server installation failed for '{$this->server->name}': {$result->errorOutput()}");

            $this->fail(new \RuntimeException('SteamCMD failed: '.$result->errorOutput()));
        }
    }
}
