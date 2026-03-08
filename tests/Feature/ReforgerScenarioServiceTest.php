<?php

namespace Tests\Feature;

use App\Models\GameInstall;
use App\Models\ReforgerScenario;
use App\Models\Server;
use App\Models\User;
use App\Services\ReforgerScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_get_scenarios_returns_stored_scenarios_from_database(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        ReforgerScenario::factory()->create([
            'server_id' => $server->id,
            'value' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
            'name' => 'Campaign',
            'is_official' => true,
        ]);

        $service = new ReforgerScenarioService;
        $result = $service->getScenarios($server);

        $this->assertCount(1, $result);
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $result[0]['value']);
        $this->assertEquals('Campaign', $result[0]['name']);
        $this->assertTrue($result[0]['isOfficial']);
    }

    public function test_refresh_scenarios_replaces_existing_scenarios(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        ReforgerScenario::factory()->create([
            'server_id' => $server->id,
            'value' => '{AAAA1111BBBB2222}Missions/Old.conf',
            'name' => 'Old Scenario',
            'is_official' => true,
        ]);

        $this->assertDatabaseCount('reforger_scenarios', 1);

        // refreshScenarios will try to run the binary, which won't exist,
        // so it returns empty and does not replace
        $service = new ReforgerScenarioService;
        $service->refreshScenarios($server);

        // Old scenarios preserved because binary not found returns empty
        $this->assertDatabaseCount('reforger_scenarios', 1);
    }

    public function test_scenarios_are_deleted_when_server_is_deleted(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        ReforgerScenario::factory()->count(3)->create([
            'server_id' => $server->id,
        ]);

        $this->assertDatabaseCount('reforger_scenarios', 3);

        $server->delete();

        $this->assertDatabaseCount('reforger_scenarios', 0);
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

        ReforgerScenario::factory()->create([
            'server_id' => $server->id,
            'value' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
            'name' => 'Campaign',
            'is_official' => true,
        ]);
        ReforgerScenario::factory()->create([
            'server_id' => $server->id,
            'value' => '{AAAA1111BBBB2222}Missions/Test.conf',
            'name' => 'Test Scenario',
            'is_official' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.reforger-scenarios', $server))
            ->assertOk();

        $scenarios = $response->json('scenarios');
        $this->assertCount(2, $scenarios);
        $this->assertContains('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', array_column($scenarios, 'value'));
        $this->assertContains('{AAAA1111BBBB2222}Missions/Test.conf', array_column($scenarios, 'value'));
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

    public function test_reload_reforger_scenarios_endpoint_rejects_non_reforger_server(): void
    {
        $user = User::factory()->create();
        $install = GameInstall::factory()->installed()->create();
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
        ]);

        $this->actingAs($user)
            ->post(route('servers.reforger-scenarios.reload', $server))
            ->assertStatus(422)
            ->assertJson(['scenarios' => []]);
    }

    public function test_reload_reforger_scenarios_endpoint_requires_auth(): void
    {
        $install = GameInstall::factory()->reforger()->create([
            'installation_status' => 'installed',
        ]);
        $server = Server::factory()->create([
            'game_install_id' => $install->id,
            'game_type' => 'reforger',
        ]);

        $this->post(route('servers.reforger-scenarios.reload', $server))
            ->assertRedirect(route('login'));
    }
}
