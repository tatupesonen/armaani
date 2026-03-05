<?php

namespace Tests\Feature\Servers;

use App\Models\GameInstall;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerProcessServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServerProcessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ServerProcessService;
    }

    public function test_generate_server_config_writes_file_to_profiles_path(): void
    {
        $server = $this->makeServer([
            'name' => 'My Test Server',
            'password' => 'secret',
            'admin_password' => 'adminpass',
            'max_players' => 32,
            'description' => null,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $this->assertFileExists($profilesPath.'/server.cfg');

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "My Test Server";', $contents);
        $this->assertStringContainsString('password = "secret";', $contents);
        $this->assertStringContainsString('passwordAdmin = "adminpass";', $contents);
        $this->assertStringContainsString('maxPlayers = 32;', $contents);
    }

    public function test_generate_server_config_empty_password_fields_are_empty_strings(): void
    {
        $server = $this->makeServer([
            'password' => null,
            'admin_password' => null,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('password = "";', $contents);
        $this->assertStringContainsString('passwordAdmin = "";', $contents);
    }

    public function test_generate_server_config_includes_motd_when_description_set(): void
    {
        $server = $this->makeServer([
            'description' => "Welcome to the server\nHave fun",
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('motd[] = {', $contents);
        $this->assertStringContainsString('"Welcome to the server"', $contents);
        $this->assertStringContainsString('"Have fun"', $contents);
    }

    public function test_generate_server_config_no_motd_when_description_empty(): void
    {
        $server = $this->makeServer(['description' => null]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('motd[]', $contents);
    }

    public function test_generate_server_config_overwrites_existing_file(): void
    {
        $server = $this->makeServer(['name' => 'Original Name']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);
        file_put_contents($profilesPath.'/server.cfg', 'old content');

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('old content', $contents);
        $this->assertStringContainsString('hostname = "Original Name";', $contents);
    }

    public function test_generate_server_config_escapes_special_characters_in_name(): void
    {
        $server = $this->makeServer(['name' => 'Server with "quotes"']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "Server with \\"quotes\\"";', $contents);
    }

    protected function tearDown(): void
    {
        // Clean up temp profiles directories created during tests
        $servers = Server::all();
        foreach ($servers as $server) {
            $profilesPath = $server->getProfilesPath();
            if (is_dir($profilesPath)) {
                @unlink($profilesPath.'/server.cfg');
                @rmdir($profilesPath);
            }
        }

        parent::tearDown();
    }

    private function makeServer(array $attributes = []): Server
    {
        $gameInstall = GameInstall::factory()->installed()->create();

        return Server::factory()->create(array_merge(
            ['game_install_id' => $gameInstall->id],
            $attributes
        ));
    }

    private function invokeGenerateServerConfig(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'generateServerConfig');
        $reflection->invoke($this->service, $server);
    }
}
