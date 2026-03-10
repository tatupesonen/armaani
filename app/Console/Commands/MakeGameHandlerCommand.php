<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:game-handler')]
class MakeGameHandlerCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'make:game-handler';

    /**
     * @var string
     */
    protected $description = 'Create a new game handler class';

    /**
     * @var string
     */
    protected $type = 'GameHandler';

    protected function getStub(): string
    {
        $stub = $this->option('steam')
            ? '/stubs/game-handler.steam.stub'
            : '/stubs/game-handler.stub';

        return $this->resolveStubPath($stub);
    }

    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.'/../../../'.trim($stub, '/');
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\GameHandlers';
    }

    protected function getNameInput(): string
    {
        $name = trim($this->argument('name'));

        if (! Str::endsWith($name, 'Handler')) {
            $name .= 'Handler';
        }

        return $name;
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $handlerName = class_basename($name);
        $gameName = Str::replaceLast('Handler', '', $handlerName);
        $gameType = Str::lower($gameName);

        $gameLabel = $this->option('label') ?? $gameName;

        $stub = str_replace('{{ gameType }}', $gameType, $stub);
        $stub = str_replace('{{ gameLabel }}', $gameLabel, $stub);

        $stub = $this->replaceSettingsPlaceholders($stub, $gameName, $gameType);
        $stub = $this->replaceExtraPlaceholders($stub, $gameType, $gameLabel);

        return $stub;
    }

    private function replaceSettingsPlaceholders(string $stub, string $gameName, string $gameType): string
    {
        if ($this->option('settings')) {
            $settingsClass = $gameName.'Settings';
            $stub = str_replace('{{ settingsModelReturn }}', "return \\App\\Models\\{$settingsClass}::class;", $stub);
            $stub = str_replace('{{ settingsRelationReturn }}', "return '{$gameType}Settings';", $stub);
            $stub = str_replace(
                '{{ createRelatedSettings }}',
                "\\App\\Models\\{$settingsClass}::query()->create(['server_id' => \$server->id]);",
                $stub,
            );
            $stub = str_replace(
                '{{ updateRelatedSettings }}',
                "\$fields = collect(\$validated)->only(\n            (new \\App\\Models\\{$settingsClass})->getFillable()\n        )->except('server_id')->toArray();\n\n        if (! empty(\$fields)) {\n            \$server->{$gameType}Settings()->updateOrCreate(\n                ['server_id' => \$server->id],\n                \$fields,\n            );\n        }",
                $stub,
            );
        } else {
            $stub = str_replace('{{ settingsModelReturn }}', 'return null;', $stub);
            $stub = str_replace('{{ settingsRelationReturn }}', 'return null;', $stub);
            $stub = str_replace('{{ createRelatedSettings }}', '// No game-specific settings', $stub);
            $stub = str_replace('{{ updateRelatedSettings }}', '// No game-specific settings', $stub);
        }

        return $stub;
    }

    private function replaceExtraPlaceholders(string $stub, string $gameType, string $gameLabel): string
    {
        $extraImports = [];
        $extraInterfaces = [];
        $extraMethods = [];

        if ($this->option('registered-mods')) {
            $extraImports[] = 'use App\Contracts\SupportsRegisteredMods;';
            $extraImports[] = 'use Illuminate\Database\Eloquent\Model;';
            $extraInterfaces[] = 'SupportsRegisteredMods';
            $extraMethods[] = $this->registeredModMethods($gameType);
        }

        if ($this->option('scenarios')) {
            $extraImports[] = 'use App\Contracts\SupportsScenarios;';
            $extraInterfaces[] = 'SupportsScenarios';
            $extraMethods[] = $this->scenarioMethods();
        }

        $stub = str_replace(
            '{{ extraImports }}',
            count($extraImports) ? implode("\n", $extraImports) : '',
            $stub,
        );

        $stub = str_replace(
            '{{ extraInterfaces }}',
            count($extraInterfaces) ? ', '.implode(', ', $extraInterfaces) : '',
            $stub,
        );

        $stub = str_replace(
            '{{ extraMethods }}',
            count($extraMethods) ? "\n".implode("\n", $extraMethods) : '',
            $stub,
        );

        return $stub;
    }

    private function registeredModMethods(string $gameType): string
    {
        return <<<'PHP'
    // --- SupportsRegisteredMods ---

    public function registeredModModelClass(): string
    {
        // TODO: Create and return your registered mod model class
        throw new \RuntimeException('registeredModModelClass() not yet implemented.');
    }

    public function registeredModRelationName(): string
    {
        // TODO: Return the relationship name for registered mods on ModPreset
        throw new \RuntimeException('registeredModRelationName() not yet implemented.');
    }

    public function registeredModPivotTable(): string
    {
        // TODO: Return the pivot table name (e.g., 'mod_preset_your_mod')
        throw new \RuntimeException('registeredModPivotTable() not yet implemented.');
    }

    public function storeRegisteredMod(array $data): Model
    {
        // TODO: Create and return a new registered mod instance
        throw new \RuntimeException('storeRegisteredMod() not yet implemented.');
    }

    public function destroyRegisteredMod(Model $mod): void
    {
        // TODO: Delete the mod and detach from presets
        throw new \RuntimeException('destroyRegisteredMod() not yet implemented.');
    }

    public function registeredModValidationRules(): array
    {
        // TODO: Return validation rules for storing a registered mod
        return [];
    }
PHP;
    }

    private function scenarioMethods(): string
    {
        return <<<'PHP'
    // --- SupportsScenarios ---

    public function getScenarios(Server $server): array
    {
        // TODO: Return available scenarios for this server
        return [];
    }

    public function refreshScenarios(Server $server): array
    {
        // TODO: Re-discover and return scenarios
        return [];
    }
PHP;
    }

    public function handle(): ?bool
    {
        $this->promptForOptions();

        $result = parent::handle();

        if ($result === false) {
            $this->fail($this->type.' could not be created.');
        }

        if ($this->option('settings')) {
            $this->generateSettings();
        }

        $this->components->info('Next steps:');
        $this->components->bulletList([
            'Set the Steam app IDs (if applicable)',
            'Implement buildLaunchCommand() and getBinaryPath()',
            'Define settingsSchema() for the server settings UI',
            'Run php artisan game:generate-types to update TypeScript types',
        ]);

        return $result;
    }

    private function promptForOptions(): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        if (! $this->option('steam') && ! $this->option('no-steam')) {
            $this->input->setOption(
                'steam',
                confirm('Is this a Steam-based game?', default: true),
            );
        }

        if (! $this->option('settings')) {
            $this->input->setOption(
                'settings',
                confirm('Generate a settings model, migration, and factory?', default: true),
            );
        }

        if (! $this->option('label')) {
            $handlerName = $this->getNameInput();
            $gameName = Str::replaceLast('Handler', '', $handlerName);

            $label = text(
                label: 'Game display name',
                default: $gameName,
                hint: 'e.g. "Arma 3", "DayZ", "Squad"',
            );

            $this->input->setOption('label', $label);
        }

        $extras = multiselect(
            label: 'Additional capabilities',
            options: [
                'registered-mods' => 'Registered mods (GUID-based mods like Reforger)',
                'scenarios' => 'Scenario discovery (discoverable missions/scenarios)',
            ],
            hint: 'Space to select, Enter to confirm',
        );

        foreach ($extras as $extra) {
            $this->input->setOption($extra, true);
        }
    }

    private function generateSettings(): void
    {
        $handlerName = $this->getNameInput();
        $gameName = Str::replaceLast('Handler', '', $handlerName);
        $settingsClass = $gameName.'Settings';
        $tableName = Str::snake($gameName).'_settings';

        $this->generateSettingsModel($settingsClass, $tableName);
        $this->generateSettingsFactory($settingsClass);
        $this->generateSettingsMigration($tableName);
    }

    private function generateSettingsModel(string $className, string $tableName): void
    {
        $path = app_path("Models/{$className}.php");

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->warn("Model [{$className}] already exists.");

            return;
        }

        $stub = $this->files->get($this->resolveStubPath('/stubs/game-handler-settings.model.stub'));
        $stub = str_replace('{{ namespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ table }}', $tableName, $stub);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $stub);
        $this->components->info("Model [{$className}] created successfully.");
    }

    private function generateSettingsFactory(string $modelName): void
    {
        $factoryClass = $modelName.'Factory';
        $path = database_path("factories/{$factoryClass}.php");

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->warn("Factory [{$factoryClass}] already exists.");

            return;
        }

        $stub = $this->files->get($this->resolveStubPath('/stubs/game-handler-settings.factory.stub'));
        $stub = str_replace('{{ class }}', $factoryClass, $stub);
        $stub = str_replace('{{ model }}', $modelName, $stub);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $stub);
        $this->components->info("Factory [{$factoryClass}] created successfully.");
    }

    private function generateSettingsMigration(string $tableName): void
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$tableName}_table.php";
        $path = database_path("migrations/{$filename}");

        $stub = <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$tableName}', function (Blueprint \$table) {
                    \$table->id();
                    \$table->foreignId('server_id')->constrained()->cascadeOnDelete();
                    // TODO: Add your settings columns here
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$tableName}');
            }
        };
        PHP;

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $stub);
        $this->components->info("Migration [{$filename}] created successfully.");
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['steam', null, InputOption::VALUE_NONE, 'Implement SteamGameHandler interface'],
            ['no-steam', null, InputOption::VALUE_NONE, 'Explicitly skip SteamGameHandler'],
            ['settings', null, InputOption::VALUE_NONE, 'Generate a settings model, migration, and factory'],
            ['registered-mods', null, InputOption::VALUE_NONE, 'Implement SupportsRegisteredMods interface'],
            ['scenarios', null, InputOption::VALUE_NONE, 'Implement SupportsScenarios interface'],
            ['label', null, InputOption::VALUE_OPTIONAL, 'The display name for this game'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files'],
        ];
    }
}
