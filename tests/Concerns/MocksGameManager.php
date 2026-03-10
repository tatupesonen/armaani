<?php

namespace Tests\Concerns;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\ManagesModAssets;
use App\Contracts\SteamGameHandler;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\GameManager;
use Mockery;

trait MocksGameManager
{
    protected function mockGameManager(?string $gameType = null): void
    {
        $interfaces = [GameHandler::class];

        if ($gameType === 'arma3') {
            $interfaces = array_merge($interfaces, [
                DetectsServerState::class,
                ManagesModAssets::class,
                SteamGameHandler::class,
                SupportsMissions::class,
                SupportsHeadlessClients::class,
                SupportsBackups::class,
            ]);
        } elseif ($gameType === 'reforger') {
            $interfaces[] = DetectsServerState::class;
            $interfaces[] = SteamGameHandler::class;
        } elseif ($gameType === 'dayz') {
            $interfaces[] = SteamGameHandler::class;
        }

        $handler = Mockery::mock(implode(', ', $interfaces));
        $handler->shouldReceive('getServerLogPath')->andReturn('/tmp/fake.log');
        $handler->shouldReceive('getBinaryPath')->andReturn('/tmp/fake_binary');
        $handler->shouldReceive('getProfileName')->andReturn('arma3_1');
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/tmp/fake_binary', '-port=2302']);
        $handler->shouldReceive('generateConfigFiles')->andReturnNull();
        $handler->shouldReceive('serverValidationRules')->andReturn([]);
        $handler->shouldReceive('settingsValidationRules')->andReturn([]);
        $handler->shouldReceive('createRelatedSettings')->andReturnNull();
        $handler->shouldReceive('updateRelatedSettings')->andReturnNull();
        $handler->shouldReceive('value')->andReturn($gameType ?? 'arma3');
        $handler->shouldReceive('label')->andReturn('Arma 3');

        if ($gameType === 'arma3') {
            $handler->shouldReceive('getBootDetectionStrings')->andReturn(['Connected to Steam servers']);
            $handler->shouldReceive('getCrashDetectionStrings')->andReturn([]);
            $handler->shouldReceive('getModDownloadStartedString')->andReturnNull();
            $handler->shouldReceive('getModDownloadFinishedString')->andReturnNull();
            $handler->shouldReceive('shouldAutoRestart')->andReturn(false);
            $handler->shouldReceive('symlinkMods')->andReturnNull();
            $handler->shouldReceive('symlinkMissions')->andReturnNull();
            $handler->shouldReceive('copyBiKeys')->andReturnNull();
            $handler->shouldReceive('buildHeadlessClientCommand')->andReturn(['/tmp/fake_binary', '-client']);
            $handler->shouldReceive('getBackupFilePath')->andReturn('/fake/backup/path');
            $handler->shouldReceive('getBackupDownloadFilename')->andReturn('backup_file');
        } elseif ($gameType === 'reforger') {
            $handler->shouldReceive('getBootDetectionStrings')->andReturn(['Server registered with addr']);
            $handler->shouldReceive('getCrashDetectionStrings')->andReturn([]);
            $handler->shouldReceive('getModDownloadStartedString')->andReturn('Addon Download started');
            $handler->shouldReceive('getModDownloadFinishedString')->andReturn('Required addons are ready to use.');
            $handler->shouldReceive('shouldAutoRestart')->andReturn(false);
        }

        $manager = Mockery::mock(GameManager::class);
        $manager->shouldReceive('for')->andReturn($handler);
        $manager->shouldReceive('driver')->andReturn($handler);
        $manager->shouldReceive('allHandlers')->andReturn([$gameType ?? 'arma3' => $handler]);
        $manager->shouldReceive('availableTypes')->andReturn([$gameType ?? 'arma3']);
        $this->app->instance(GameManager::class, $manager);
    }
}
