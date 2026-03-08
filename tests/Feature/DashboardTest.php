<?php

namespace Tests\Feature;

use App\Enums\InstallationStatus;
use App\Enums\ServerStatus;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\SteamAccount;
use App\Models\User;
use App\Models\WorkshopMod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    }

    public function test_dashboard_returns_all_expected_props(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('serverStats')
                ->has('gameInstallStats')
                ->has('modStats')
                ->has('presetCount')
                ->has('missionCount')
                ->has('queueStats')
                ->has('steamConfigured')
                ->has('servers')
                ->has('diskUsage')
                ->has('memoryUsage')
                ->has('cpuInfo')
            );
    }

    public function test_dashboard_shows_correct_server_stats(): void
    {
        $gameInstall = GameInstall::factory()->installed()->create();
        Server::factory()->create([
            'game_install_id' => $gameInstall->id,
            'status' => ServerStatus::Running,
        ]);
        Server::factory()->create([
            'game_install_id' => $gameInstall->id,
            'status' => ServerStatus::Stopped,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serverStats.total', 2)
                ->where('serverStats.running', 1)
                ->where('serverStats.stopped', 1)
            );
    }

    public function test_dashboard_shows_correct_game_install_stats(): void
    {
        GameInstall::factory()->installed()->create();
        GameInstall::factory()->create(['installation_status' => InstallationStatus::Queued]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('gameInstallStats.total', 2)
                ->where('gameInstallStats.installed', 1)
            );
    }

    public function test_dashboard_shows_correct_mod_stats(): void
    {
        WorkshopMod::factory()->installed()->count(3)->create();
        WorkshopMod::factory()->create(['installation_status' => InstallationStatus::Queued]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('modStats.total', 4)
                ->where('modStats.installed', 3)
            );
    }

    public function test_dashboard_shows_preset_count(): void
    {
        ModPreset::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('presetCount', 3)
            );
    }

    public function test_dashboard_shows_steam_configured_status(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('steamConfigured', false)
            );

        SteamAccount::factory()->create();

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('steamConfigured', true)
            );
    }

    public function test_dashboard_lists_servers_with_game_install(): void
    {
        $gameInstall = GameInstall::factory()->installed()->create();
        Server::factory()->create([
            'game_install_id' => $gameInstall->id,
            'name' => 'Test Server',
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('servers', 1)
                ->where('servers.0.name', 'Test Server')
                ->has('servers.0.game_install')
            );
    }

    public function test_dashboard_returns_system_resource_info(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('diskUsage.total')
                ->has('diskUsage.used')
                ->has('diskUsage.free')
                ->has('diskUsage.percent')
                ->has('cpuInfo.load_1')
                ->has('cpuInfo.cores')
            );
    }
}
