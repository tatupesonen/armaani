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
use Livewire\Livewire;
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
        $this->actingAs($this->user);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_displays_server_stats(): void
    {
        $this->actingAs($this->user);

        $gameInstall = GameInstall::factory()->installed()->create();

        Server::factory()->for($gameInstall)->create(['status' => ServerStatus::Running]);
        Server::factory()->for($gameInstall)->create(['status' => ServerStatus::Stopped]);
        Server::factory()->for($gameInstall)->create(['status' => ServerStatus::Stopped]);

        Livewire::test('pages::dashboard')
            ->assertSee('Servers')
            ->assertSee('3')
            ->assertSee('1 running')
            ->assertSee('2 stopped');
    }

    public function test_dashboard_displays_game_install_stats(): void
    {
        $this->actingAs($this->user);

        GameInstall::factory()->installed()->create([
            'name' => 'Stable Install',
            'disk_size_bytes' => 5368709120, // 5 GB
        ]);

        Livewire::test('pages::dashboard')
            ->assertSee('Game Installs')
            ->assertSee('1')
            ->assertSee('5.0 GB');
    }

    public function test_dashboard_displays_mod_stats(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->installed()->create(['file_size' => 104857600]); // 100 MB
        WorkshopMod::factory()->installed()->create(['file_size' => 209715200]); // 200 MB
        WorkshopMod::factory()->create(['installation_status' => InstallationStatus::Queued]);

        Livewire::test('pages::dashboard')
            ->assertSee('Workshop Mods')
            ->assertSee('2') // installed count
            ->assertSee('300.0 MB');
    }

    public function test_dashboard_displays_system_resources(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::dashboard')
            ->assertSee('Disk Usage')
            ->assertSee('Memory')
            ->assertSee('CPU Load')
            ->assertSee('cores');
    }

    public function test_dashboard_displays_server_status_overview(): void
    {
        $this->actingAs($this->user);

        $gameInstall = GameInstall::factory()->installed()->create();

        Server::factory()->for($gameInstall)->create([
            'name' => 'Alpha Server',
            'port' => 2302,
            'status' => ServerStatus::Running,
        ]);

        Livewire::test('pages::dashboard')
            ->assertSee('Server Status')
            ->assertSee('Alpha Server')
            ->assertSee(':2302')
            ->assertSee('Running');
    }

    public function test_dashboard_shows_empty_server_state(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::dashboard')
            ->assertSee('No servers configured')
            ->assertSee('Create your first server');
    }

    public function test_dashboard_displays_quick_info_section(): void
    {
        $this->actingAs($this->user);

        ModPreset::factory()->count(3)->create();

        Livewire::test('pages::dashboard')
            ->assertSee('Quick Info')
            ->assertSee('Mod Presets')
            ->assertSee('Queue Jobs')
            ->assertSee('Failed Jobs');
    }

    public function test_dashboard_shows_steam_configured_status(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create();

        Livewire::test('pages::dashboard')
            ->assertSee('Steam Account')
            ->assertSee('Configured');
    }

    public function test_dashboard_shows_steam_not_configured_status(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::dashboard')
            ->assertSee('Steam Account')
            ->assertSee('Not configured');
    }

    public function test_dashboard_formats_bytes_correctly(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('pages::dashboard');

        $instance = $component->instance();

        $this->assertSame('0 KB', $instance->formatBytes(0));
        $this->assertSame('512 KB', $instance->formatBytes(524288));
        $this->assertSame('1.5 MB', $instance->formatBytes(1572864));
        $this->assertSame('2.5 GB', $instance->formatBytes(2684354560));
        $this->assertSame('1.50 TB', $instance->formatBytes(1649267441664));
    }

    public function test_dashboard_usage_bar_color_thresholds(): void
    {
        $this->actingAs($this->user);

        $instance = Livewire::test('pages::dashboard')->instance();

        $this->assertSame('bg-emerald-500', $instance->usageBarColor(50.0));
        $this->assertSame('bg-emerald-500', $instance->usageBarColor(74.9));
        $this->assertSame('bg-amber-500', $instance->usageBarColor(75.0));
        $this->assertSame('bg-amber-500', $instance->usageBarColor(89.9));
        $this->assertSame('bg-red-500', $instance->usageBarColor(90.0));
        $this->assertSame('bg-red-500', $instance->usageBarColor(100.0));
    }
}
