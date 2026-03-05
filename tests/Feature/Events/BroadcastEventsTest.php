<?php

namespace Tests\Feature\Events;

use App\Events\GameInstallOutput;
use App\Events\ModDownloadOutput;
use App\Events\ServerLogOutput;
use Illuminate\Broadcasting\Channel;
use Tests\TestCase;

class BroadcastEventsTest extends TestCase
{
    public function test_game_install_output_broadcasts_on_correct_channel(): void
    {
        $event = new GameInstallOutput(
            gameInstallId: 5,
            progressPct: 42,
            line: 'Downloading update...',
        );

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertEquals('game-install.5', $channel->name);
    }

    public function test_game_install_output_has_public_properties(): void
    {
        $event = new GameInstallOutput(
            gameInstallId: 3,
            progressPct: 75,
            line: 'progress: 75.00 (100 / 133)',
        );

        $this->assertEquals(3, $event->gameInstallId);
        $this->assertEquals(75, $event->progressPct);
        $this->assertEquals('progress: 75.00 (100 / 133)', $event->line);
    }

    public function test_mod_download_output_broadcasts_on_correct_channel(): void
    {
        $event = new ModDownloadOutput(
            modId: 10,
            progressPct: 55,
            line: 'Downloading... 55%',
        );

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertEquals('mod-download.10', $channel->name);
    }

    public function test_mod_download_output_has_public_properties(): void
    {
        $event = new ModDownloadOutput(
            modId: 7,
            progressPct: 0,
            line: 'Starting SteamCMD download...',
        );

        $this->assertEquals(7, $event->modId);
        $this->assertEquals(0, $event->progressPct);
        $this->assertEquals('Starting SteamCMD download...', $event->line);
    }

    public function test_server_log_output_broadcasts_on_correct_channel(): void
    {
        $event = new ServerLogOutput(
            serverId: 2,
            line: '12:00:00 Server started',
        );

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertEquals('server-log.2', $channel->name);
    }

    public function test_server_log_output_has_public_properties(): void
    {
        $event = new ServerLogOutput(
            serverId: 4,
            line: 'Player connected',
        );

        $this->assertEquals(4, $event->serverId);
        $this->assertEquals('Player connected', $event->line);
    }
}
