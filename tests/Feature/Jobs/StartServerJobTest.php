<?php

namespace Tests\Feature\Jobs;

use App\Enums\ServerStatus;
use App\Events\ServerStatusChanged;
use App\Jobs\StartServerJob;
use App\Models\Server;
use App\Services\Server\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class StartServerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_job_sets_starting_then_booting_status(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Test Server', 'status' => ServerStatus::Stopped]);

        $service = Mockery::mock(ServerProcessService::class);
        $service->shouldReceive('start')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));
        $service->shouldReceive('isRunning')->once()->andReturnTrue();
        $this->app->instance(ServerProcessService::class, $service);

        (new StartServerJob($server))->handle($service);

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'starting'
                && $event->serverName === 'Test Server';
        });

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'booting'
                && $event->serverName === 'Test Server';
        });
    }

    public function test_start_job_sets_stopped_when_process_fails_to_start(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Fail Server', 'status' => ServerStatus::Stopped]);

        $service = Mockery::mock(ServerProcessService::class);
        $service->shouldReceive('start')->once();
        $service->shouldReceive('isRunning')->once()->andReturnFalse();
        $this->app->instance(ServerProcessService::class, $service);

        (new StartServerJob($server))->handle($service);

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'starting'
                && $event->serverName === 'Fail Server';
        });

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'stopped'
                && $event->serverName === 'Fail Server';
        });
    }

    public function test_failed_method_sets_stopped_status(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Failed Server', 'status' => ServerStatus::Starting]);

        $job = new StartServerJob($server);
        $job->failed(new \RuntimeException('Something went wrong'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'stopped'
                && $event->serverName === 'Failed Server';
        });
    }
}
