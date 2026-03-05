<?php

namespace Tests\Feature\Jobs;

use App\Enums\ServerStatus;
use App\Jobs\StartServerJob;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StartServerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_job_calls_service_and_sets_running_status(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Starting]);

        $service = Mockery::mock(ServerProcessService::class);
        $service->shouldReceive('start')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));
        $service->shouldReceive('isRunning')->once()->andReturnTrue();
        $this->app->instance(ServerProcessService::class, $service);

        (new StartServerJob($server))->handle($service);

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);
    }

    public function test_start_job_sets_stopped_when_process_fails_to_start(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Starting]);

        $service = Mockery::mock(ServerProcessService::class);
        $service->shouldReceive('start')->once();
        $service->shouldReceive('isRunning')->once()->andReturnFalse();
        $this->app->instance(ServerProcessService::class, $service);

        (new StartServerJob($server))->handle($service);

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);
    }

    public function test_restart_job_stops_then_starts_server(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Stopping]);

        $service = Mockery::mock(ServerProcessService::class);
        $service->shouldReceive('stop')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));
        $service->shouldReceive('start')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));
        $service->shouldReceive('isRunning')->once()->andReturnTrue();
        $this->app->instance(ServerProcessService::class, $service);

        (new StartServerJob($server, restart: true))->handle($service);

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);
    }

    public function test_failed_method_sets_stopped_status(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Starting]);

        $job = new StartServerJob($server);
        $job->failed(new \RuntimeException('Something went wrong'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);
    }
}
