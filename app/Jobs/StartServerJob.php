<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Server $server) {}

    public function handle(ServerProcessService $service): void
    {
        $this->server->transitionTo(ServerStatus::Starting);

        Log::info("{$this->server->logContext()} Starting server via queued job");
        $service->start($this->server);

        if ($service->isRunning($this->server)) {
            $this->server->transitionTo(ServerStatus::Booting);
            Log::info("{$this->server->logContext()} Server process started, booting (waiting for Steam connection)");
        } else {
            $this->server->transitionTo(ServerStatus::Stopped);
            Log::error("{$this->server->logContext()} Server failed to start");
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->server->transitionTo(ServerStatus::Stopped);
        Log::error("{$this->server->logContext()} StartServerJob failed: {$exception?->getMessage()}");
    }
}
