<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeGameHandlerCommandTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $generatedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    private function trackFile(string $path): void
    {
        $this->generatedFiles[] = $path;
    }

    public function test_creates_basic_handler(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertFileExists($path);
        $content = File::get($path);
        $this->assertStringContainsString('class SquadHandler implements GameHandler', $content);
        $this->assertStringNotContainsString('SteamGameHandler', $content);
        $this->assertStringContainsString("return 'squad';", $content);
        $this->assertStringContainsString("return 'Squad';", $content);
    }

    public function test_appends_handler_suffix_if_missing(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertFileExists($path);
    }

    public function test_does_not_double_handler_suffix(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'SquadHandler', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertFileDoesNotExist(app_path('GameHandlers/SquadHandlerHandler.php'));
    }

    public function test_steam_flag_includes_steam_interface(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--steam' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $content = File::get($path);
        $this->assertStringContainsString('SteamGameHandler', $content);
        $this->assertStringContainsString('function serverAppId(): int', $content);
        $this->assertStringContainsString('function gameId(): int', $content);
        $this->assertStringContainsString('function consumerAppId(): int', $content);
    }

    public function test_settings_flag_generates_model_factory_and_migration(): void
    {
        $handlerPath = app_path('GameHandlers/SquadHandler.php');
        $modelPath = app_path('Models/SquadSettings.php');
        $factoryPath = database_path('factories/SquadSettingsFactory.php');
        $this->trackFile($handlerPath);
        $this->trackFile($modelPath);
        $this->trackFile($factoryPath);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--settings' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertFileExists($modelPath);
        $modelContent = File::get($modelPath);
        $this->assertStringContainsString("protected \$table = 'squad_settings';", $modelContent);
        $this->assertStringContainsString('function server(): BelongsTo', $modelContent);

        $this->assertFileExists($factoryPath);
        $factoryContent = File::get($factoryPath);
        $this->assertStringContainsString('SquadSettings', $factoryContent);
        $this->assertStringContainsString('Server::factory()', $factoryContent);

        // Handler should reference the settings model
        $handlerContent = File::get($handlerPath);
        $this->assertStringContainsString('SquadSettings::class', $handlerContent);
        $this->assertStringContainsString("return 'squadSettings';", $handlerContent);

        // Clean up migration (filename has timestamp)
        foreach (File::glob(database_path('migrations/*_create_squad_settings_table.php')) as $migration) {
            $this->trackFile($migration);
        }
    }

    public function test_registered_mods_flag_adds_interface(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--registered-mods' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $content = File::get($path);
        $this->assertStringContainsString('SupportsRegisteredMods', $content);
        $this->assertStringContainsString('function registeredModModelClass(): string', $content);
        $this->assertStringContainsString('function registeredModPivotTable(): string', $content);
    }

    public function test_scenarios_flag_adds_interface(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--scenarios' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $content = File::get($path);
        $this->assertStringContainsString('SupportsScenarios', $content);
        $this->assertStringContainsString('function getScenarios(Server $server): array', $content);
        $this->assertStringContainsString('function refreshScenarios(Server $server): array', $content);
    }

    public function test_label_option_sets_custom_label(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--label' => 'Squad 44', '--no-interaction' => true])
            ->assertSuccessful();

        $content = File::get($path);
        $this->assertStringContainsString("return 'Squad 44';", $content);
    }

    public function test_refuses_to_overwrite_without_force(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--no-interaction' => true])
            ->assertSuccessful();

        $this->artisan('make:game-handler', ['name' => 'Squad', '--no-interaction' => true])
            ->assertFailed();
    }

    public function test_force_flag_overwrites_existing(): void
    {
        $path = app_path('GameHandlers/SquadHandler.php');
        $this->trackFile($path);

        $this->artisan('make:game-handler', ['name' => 'Squad', '--no-interaction' => true])
            ->assertSuccessful();

        $this->artisan('make:game-handler', ['name' => 'Squad', '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();
    }
}
