<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Events\ServerStatusChanged;
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
        $context = "[Server:{$this->server->id} '{$this->server->name}']";

        $this->server->update(['status' => ServerStatus::Starting]);
        ServerStatusChanged::dispatch($this->server->id, ServerStatus::Starting->value, $this->server->name);

        Log::info("{$context} Starting server via queued job");
        $service->start($this->server);

        if ($service->isRunning($this->server)) {
            $this->server->update(['status' => ServerStatus::Booting]);
            ServerStatusChanged::dispatch($this->server->id, ServerStatus::Booting->value, $this->server->name);
            Log::info("{$context} Server process started, booting (waiting for Steam connection)");
        } else {
            $this->server->update(['status' => ServerStatus::Stopped]);
            ServerStatusChanged::dispatch($this->server->id, ServerStatus::Stopped->value, $this->server->name);
            Log::error("{$context} Server failed to start");
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->server->update(['status' => ServerStatus::Stopped]);
        ServerStatusChanged::dispatch($this->server->id, ServerStatus::Stopped->value, $this->server->name);
        Log::error("[Server:{$this->server->id}] StartServerJob failed: {$exception?->getMessage()}");
    }
}
