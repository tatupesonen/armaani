<?php

namespace Tests\Feature\Jobs;

use App\Enums\ServerStatus;
use App\Events\ServerStatusChanged;
use App\Jobs\StopServerJob;
use App\Models\Server;
use App\Services\Server\ServerProcessService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class StopServerJobTest extends TestCase
{
    public function test_stop_job_calls_service_and_sets_stopped_status(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Stop Server', 'status' => ServerStatus::Stopping]);

        $service = $this->mock(ServerProcessService::class, function (MockInterface $mock) use ($server) {
            $mock->shouldReceive('stopAllHeadlessClients')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));
            $mock->shouldReceive('stop')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));
        });

        (new StopServerJob($server))->handle($service);

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'stopped'
                && $event->serverName === 'Stop Server';
        });
    }

    public function test_stop_job_stops_headless_clients_before_server(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['status' => ServerStatus::Stopping]);

        $callOrder = [];
        $service = $this->mock(ServerProcessService::class, function (MockInterface $mock) use (&$callOrder) {
            $mock->shouldReceive('stopAllHeadlessClients')->once()->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'stopAllHeadlessClients';
            });
            $mock->shouldReceive('stop')->once()->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'stop';
            });
        });

        (new StopServerJob($server))->handle($service);

        $this->assertEquals(['stopAllHeadlessClients', 'stop'], $callOrder);
    }

    public function test_failed_method_sets_stopped_status(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Failed Stop', 'status' => ServerStatus::Stopping]);

        $job = new StopServerJob($server);
        $job->failed(new \RuntimeException('Something went wrong'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'stopped'
                && $event->serverName === 'Failed Stop';
        });
    }
}
