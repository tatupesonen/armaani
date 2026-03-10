<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\Server\ServerProcessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StopServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public Server $server) {}

    public function handle(ServerProcessService $service): void
    {
        Log::info("{$this->server->logContext()} Stopping server via queued job");
        $service->stopAllHeadlessClients($this->server);
        $service->stop($this->server);

        $this->server->transitionTo(ServerStatus::Stopped);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->server->transitionTo(ServerStatus::Stopped);
        Log::error("{$this->server->logContext()} StopServerJob failed: {$exception?->getMessage()}");
    }
}
