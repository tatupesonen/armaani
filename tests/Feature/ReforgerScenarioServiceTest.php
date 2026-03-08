<?php

namespace Tests\Feature;

use App\Models\GameInstall;
use App\Models\Server;
use App\Models\User;
use App\Services\ReforgerScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReforgerScenarioServiceTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Service unit tests
    // ---------------------------------------------------------------

    public function test_parse_line_extracts_scenario_id_and_name(): void
    {
        $service = new ReforgerScenarioService;

        $method = new \ReflectionMethod($service, 'parseLine');

        $result = $method->invoke($service, '{ECC61978EDCC2B5A}Missions/23_Campaign.conf Campaign');
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $result['value']);
        $this->assertEquals('Campaign', $result['name']);
    }

    public function test_parse_line_handles_name_with_parentheses(): void
    {
        $service = new ReforgerScenarioService;
        $method = new \ReflectionMethod($service, 'parseLine');

        $result = $method->invoke($service, '{AAAA1111BBBB2222}Missions/Test.conf (Test Scenario)');
        $this->assertEquals('{AAAA1111BBBB2222}Missions/Test.conf', $result['value']);
        $this->assertEquals('Test Scenario', $result['name']);
    }

    public function test_parse_line_uses_value_as_name_when_no_name(): void
    {
        $service = new ReforgerScenarioService;
        $method = new \ReflectionMethod($service, 'parseLine');

        $result = $method->invoke($service, '{AAAA1111BBBB2222}Missions/Test.conf');
        $this->assertEquals('{AAAA1111BBBB2222}Missions/Test.conf', $result['value']);
        $this->assertEquals('{AAAA1111BBBB2222}Missions/Test.conf', $result['name']);
    }

    public function test_parse_line_strips_log_prefix(): void
    {
        $service = new ReforgerScenarioService;
        $method = new \ReflectionMethod($service, 'parseLine');

        $result = $method->invoke($service, 'SCRIPT       : {ECC61978EDCC2B5A}Missions/23_Campaign.conf Campaign');
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $result['value']);
        $this->assertEquals('Campaign', $result['name']);
    }

    public function test_parse_line_returns_null_for_non_scenario_lines(): void
    {
        $service = new ReforgerScenarioService;
        $method = new \ReflectionMethod($service, 'parseLine');

        $this->assertNull($method->invoke($service, 'Some random log line'));
        $this->assertNull($method->invoke($service, ''));
    }

    public function test_get_scenarios_returns_empty_when_binary_not_found(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        $service = new ReforgerScenarioService;
        $result = $service->getScenarios($server);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_scenarios_caches_results(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        $service = new ReforgerScenarioService;

        // First call populates cache
        $service->getScenarios($server);

        $this->assertTrue(Cache::has('reforger_scenarios_'.$server->id));
    }

    public function test_clear_cache_removes_cached_scenarios(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        Cache::put('reforger_scenarios_'.$server->id, [['value' => 'test', 'name' => 'Test', 'isOfficial' => true]], 300);
        $this->assertTrue(Cache::has('reforger_scenarios_'.$server->id));

        $service = new ReforgerScenarioService;
        $service->clearCache($server);

        $this->assertFalse(Cache::has('reforger_scenarios_'.$server->id));
    }

    // ---------------------------------------------------------------
    // Controller endpoint tests
    // ---------------------------------------------------------------

    public function test_reforger_scenarios_endpoint_returns_scenarios(): void
    {
        $user = User::factory()->create();
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        // Pre-seed cache to avoid needing a real binary
        Cache::put('reforger_scenarios_'.$server->id, [
            ['value' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf', 'name' => 'Campaign', 'isOfficial' => true],
            ['value' => '{AAAA1111BBBB2222}Missions/Test.conf', 'name' => 'Test Scenario', 'isOfficial' => false],
        ], 300);

        $this->actingAs($user)
            ->get(route('servers.reforger-scenarios', $server))
            ->assertOk()
            ->assertJson([
                'scenarios' => [
                    ['value' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf', 'name' => 'Campaign', 'isOfficial' => true],
                    ['value' => '{AAAA1111BBBB2222}Missions/Test.conf', 'name' => 'Test Scenario', 'isOfficial' => false],
                ],
            ]);
    }

    public function test_reforger_scenarios_endpoint_rejects_non_reforger_server(): void
    {
        $user = User::factory()->create();
        $install = GameInstall::factory()->installed()->create();
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
        ]);

        $this->actingAs($user)
            ->get(route('servers.reforger-scenarios', $server))
            ->assertStatus(422)
            ->assertJson(['scenarios' => []]);
    }

    public function test_reforger_scenarios_endpoint_requires_auth(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        $this->get(route('servers.reforger-scenarios', $server))
            ->assertRedirect(route('login'));
    }
}
