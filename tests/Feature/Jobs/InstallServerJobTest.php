<?php

namespace Tests\Feature\Jobs;

use App\Contracts\GameServerInstaller;
use App\Enums\InstallationStatus;
use App\Events\GameInstallOutput;
use App\Jobs\InstallServerJob;
use App\Models\GameInstall;
use App\Models\SteamAccount;
use App\Services\Installers\InstallerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class InstallServerJobTest extends TestCase
{
    use RefreshDatabase;

    private string $testGamesBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testGamesBasePath = sys_get_temp_dir().'/armaani_test_games_'.uniqid();

        config(['arma.games_base_path' => $this->testGamesBasePath]);

        SteamAccount::factory()->create();
        Event::fake([GameInstallOutput::class]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_successful_install_updates_status_and_broadcasts(): void
    {
        $install = GameInstall::factory()->create([
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result("5000000\t/fake/path")]);

        $mockInstaller = $this->createMock(GameServerInstaller::class);
        $mockInstaller->expects($this->once())
            ->method('install')
            ->willReturn('15873241');

        $this->mockInstallerResolver($mockInstaller);

        $job = new InstallServerJob($install);
        $job->handle();

        $install->refresh();
        $this->assertEquals(InstallationStatus::Installed, $install->installation_status);
        $this->assertEquals('15873241', $install->build_id);
        $this->assertEquals(100, $install->progress_pct);
        $this->assertNotNull($install->installed_at);

        Event::assertDispatched(GameInstallOutput::class);
    }

    public function test_successful_install_with_null_build_id(): void
    {
        $install = GameInstall::factory()->create([
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result("5000000\t/fake/path")]);

        $mockInstaller = $this->createMock(GameServerInstaller::class);
        $mockInstaller->expects($this->once())
            ->method('install')
            ->willReturn(null);

        $this->mockInstallerResolver($mockInstaller);

        $job = new InstallServerJob($install);
        $job->handle();

        $install->refresh();
        $this->assertEquals(InstallationStatus::Installed, $install->installation_status);
        $this->assertNull($install->build_id);
    }

    public function test_failed_install_marks_status_as_failed(): void
    {
        $install = GameInstall::factory()->create([
            'installation_status' => InstallationStatus::Queued,
        ]);

        $mockInstaller = $this->createMock(GameServerInstaller::class);
        $mockInstaller->expects($this->once())
            ->method('install')
            ->willThrowException(new \RuntimeException('Download failed'));

        $this->mockInstallerResolver($mockInstaller);

        $job = (new InstallServerJob($install))->withFakeQueueInteractions();
        $job->handle();

        $install->refresh();
        $this->assertEquals(InstallationStatus::Failed, $install->installation_status);
        $this->assertNull($install->build_id);

        $job->assertFailed();
    }

    /**
     * Bind a mock InstallerResolver that always returns the given installer.
     */
    private function mockInstallerResolver(GameServerInstaller $installer): void
    {
        $resolver = $this->createMock(InstallerResolver::class);
        $resolver->method('resolve')->willReturn($installer);

        $this->app->instance(InstallerResolver::class, $resolver);
    }
}
