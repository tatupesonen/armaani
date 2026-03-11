---
name: game-handler-development
description: 'Activates when adding a new game to the server manager. Use when creating game handlers, implementing GameHandler/SteamGameHandler contracts, configuring server settings schemas, generating config files, or when the user mentions adding a new game, game support, or game handler.'
---

# Game Handler Development

## When to Apply

Activate this skill when:

- Adding support for a new game (e.g., Squad, Insurgency)
- Modifying an existing game handler in `app/GameHandlers/`
- Working with the `GameHandler`, `SteamGameHandler`, `SupportsWorkshopMods`, `SupportsRegisteredMods`, or `SupportsScenarios` contracts
- Creating or editing config templates in `resources/templates/configs/`
- Working with `settingsSchema()` UI definitions
- Working with the `AbstractGameHandler` base class or capability interfaces/traits
- Running `php artisan make:game-handler`

## Research First

Before implementing a game handler, **research the game's dedicated server online** to understand:

- The game's Steam App IDs (server, game, consumer) via [SteamDB](https://steamdb.info)
- What config file format the server uses (key-value `.cfg`, JSON, XML, INI, etc.)
- What server parameters and launch flags it supports
- Default ports (game port, query port)
- Whether it supports Steam Workshop mods
- Whether it has GUID-based registered mods (like Reforger)
- Whether it has discoverable scenarios/missions
- **Whether the server binary is a wrapper script** (e.g., PZ's `start-server.sh` wraps Java) — affects process management
- **Whether the server requires interactive input on first run** (e.g., PZ prompts for admin password via stdin) — must be handled via launch flags instead

Use this research to populate the handler's methods and create accurate config templates.

## Scaffolding with make:game-handler

The `make:game-handler` Artisan command scaffolds a new handler with all required boilerplate:

```bash
# Interactive mode (prompts for all options)
php artisan make:game-handler SquadServer

# Non-interactive with all flags
php artisan make:game-handler Squad --steam --settings --registered-mods --scenarios --label="Squad"
```

**Flags:**

- `--steam` — Implement `SteamGameHandler` interface (most games need this)
- `--settings` — Generate a settings model, migration, and factory
- `--registered-mods` — Implement `SupportsRegisteredMods` interface
- `--scenarios` — Implement `SupportsScenarios` interface
- `--label="Display Name"` — Human-readable game name
- `--force` — Overwrite existing files

The command auto-appends `Handler` to the name if not present.

**Generated files:**

- `app/GameHandlers/{Name}Handler.php` — The handler class
- `app/Models/{Name}Settings.php` — Settings model (if `--settings`)
- `database/factories/{Name}SettingsFactory.php` — Factory (if `--settings`)
- `database/migrations/..._create_{name}_settings_table.php` — Migration (if `--settings`)

## Architecture Overview

### AbstractGameHandler Base Class

All handlers extend `App\GameHandlers\AbstractGameHandler`, which implements the `GameHandler` interface. The base class uses constructor property promotion for identity values and provides default implementations for common methods.

**Constructor properties (all `final` getters):**

| Property               | Type                        | Purpose                                     |
| ---------------------- | --------------------------- | ------------------------------------------- |
| `value`                | `string`                    | Unique identifier (e.g., `'squad'`)         |
| `label`                | `string`                    | Display name (e.g., `'Squad'`)              |
| `defaultPort`          | `int`                       | Default game port                           |
| `defaultQueryPort`     | `int`                       | Default query port                          |
| `branches`             | `list<string>`              | SteamCMD branches (at minimum `['public']`) |
| `settingsModelClass`   | `class-string<Model>\|null` | Settings model FQCN (optional)              |
| `settingsRelationName` | `string\|null`              | Relationship name on Server (optional)      |

**Default implementations (override as needed):**

- `serverValidationRules()` — returns `[]`
- `settingsValidationRules()` — returns `[]`
- `settingsSchema()` — returns `[]`
- `createRelatedSettings()` — creates settings model row if `settingsModelClass` is set
- `updateRelatedSettings()` — updates settings via relationship if both class and relation are set
- `modSections()` — returns `[]` (no mod support)
- `syncPresetMods()` — no-op
- `getPresetModCount()` — returns `0`

**Abstract methods (must implement):**

- `getBinaryPath(Server): string`
- `getProfileName(Server): string`
- `getServerLogPath(Server): string`
- `buildLaunchCommand(Server): array`
- `generateConfigFiles(Server): void`

Example handler constructor:

```php
final class SquadHandler extends AbstractGameHandler implements SteamGameHandler
{
    public function __construct(
        protected TwigConfigRenderer $configRenderer,
    ) {
        parent::__construct(
            value: 'squad',
            label: 'Squad',
            defaultPort: 7787,
            defaultQueryPort: 27165,
            branches: ['public'],
            settingsModelClass: SquadSettings::class,
            settingsRelationName: 'squadSettings',
        );
    }
}
```

### Auto-Discovery

Game handlers are auto-discovered by `GameServiceProvider` from `app/GameHandlers/`. No manual registration needed — just create the handler class and it works. The provider automatically skips abstract classes and classes marked with the `#[Beta]` attribute in production.

### `#[Beta]` Attribute

Mark work-in-progress handlers with `#[Beta]` (`App\Attributes\Beta`):

```php
use App\Attributes\Beta;

#[Beta]
final class SquadHandler extends AbstractGameHandler implements SteamGameHandler
{
    // ...
}
```

Handlers with `#[Beta]` are:

- **Excluded** from discovery in production (`app()->isProduction()`)
- **Included** in all other environments (local, testing, staging)

Use this for scaffolded handlers that throw `RuntimeException` for unimplemented features (e.g., DayZ).

### Dynamic Relationships

`GameServiceProvider::boot()` calls `resolveRelationUsing()` on `Server` and `ModPreset` to register each handler's settings and mod relationships. The Server model has zero per-game code.

### TypeScript Type Generation

After creating or modifying a handler, run:

```bash
php artisan game:generate-types
```

This generates `resources/js/types/generated.d.ts` with a discriminated union `Server` type. The Vite plugin in `vite.config.ts` auto-runs this during dev when files in `app/GameHandlers/` change.

## Contracts Reference

### GameHandler (required — implemented by AbstractGameHandler)

Every handler must implement `App\Contracts\GameHandler`. The `AbstractGameHandler` base class implements it with constructor properties and default methods. Handlers extend the base class and override methods as needed.

Key methods on the interface:

| Method                                       | Purpose                                       | Default in AbstractGameHandler |
| -------------------------------------------- | --------------------------------------------- | ------------------------------ |
| `value(): string`                            | Unique identifier (DB value, driver key).     | `final` — from constructor     |
| `label(): string`                            | Display name.                                 | `final` — from constructor     |
| `defaultPort(): int`                         | Default game server port.                     | `final` — from constructor     |
| `defaultQueryPort(): int`                    | Default Steam query port.                     | `final` — from constructor     |
| `branches(): array`                          | SteamCMD beta branches.                       | `final` — from constructor     |
| `settingsModelClass(): ?string`              | FQCN of the settings model.                   | `final` — from constructor     |
| `settingsRelationName(): ?string`            | Relationship name on Server.                  | `final` — from constructor     |
| `buildLaunchCommand(Server): array`          | Command array to start the server process.    | **abstract**                   |
| `generateConfigFiles(Server): void`          | Write config files before server start.       | **abstract**                   |
| `getBinaryPath(Server): string`              | Path to the server executable.                | **abstract**                   |
| `getProfileName(Server): string`             | Profile directory name.                       | **abstract**                   |
| `getServerLogPath(Server): string`           | Path to the server log file.                  | **abstract**                   |
| `serverValidationRules(?Server): array`      | Validation rules for game-specific fields.    | returns `[]`                   |
| `settingsValidationRules(): array`           | Validation rules for game settings.           | returns `[]`                   |
| `settingsSchema(): array`                    | UI schema for the settings panel.             | returns `[]`                   |
| `createRelatedSettings(Server): void`        | Create default settings for new server.       | auto-creates from model class  |
| `updateRelatedSettings(Server, array): void` | Update settings from validated form data.     | auto-updates via relationship  |
| `modSections(): array`                       | Define mod UI tabs.                           | returns `[]`                   |
| `syncPresetMods(ModPreset, array): void`     | Sync mod preset relationships from form data. | no-op                          |
| `getPresetModCount(ModPreset): int`          | Total mod count for a preset.                 | returns `0`                    |

### SteamGameHandler (most games)

Implement `App\Contracts\SteamGameHandler` for Steam-based games:

| Method                 | Purpose                                                     |
| ---------------------- | ----------------------------------------------------------- |
| `serverAppId(): int`   | Dedicated server Steam App ID (for `+app_update`).          |
| `gameId(): int`        | Game ID for workshop mod paths (`+workshop_download_item`). |
| `consumerAppId(): int` | Consumer App ID for Steam Web API mod detection.            |

Look up these IDs on [SteamDB](https://steamdb.info).

### DownloadsDirectly (non-Steam games)

Implement `App\Contracts\DownloadsDirectly` for games whose server binaries are downloaded via HTTP rather than SteamCMD (e.g., Factorio). The handler declares _what_ it is; `InstallerResolver` determines _how_ to install. Handlers never import or reference installer classes.

| Method                             | Purpose                                                                                       |
| ---------------------------------- | --------------------------------------------------------------------------------------------- |
| `getDownloadUrl(string): string`   | Full URL to download the server archive for the given branch.                                 |
| `getArchiveStripComponents(): int` | Leading directory components to strip during tar extraction (e.g., 1 for `factorio/bin/...`). |

**Important:** `DownloadsDirectly` handlers do NOT implement `SteamGameHandler`. They have no Steam App IDs. The `InstallerResolver` maps `SteamGameHandler` → `SteamGameInstaller` and `DownloadsDirectly` → `HttpGameInstaller` automatically.

#### Per-server config.ini pattern

Games downloaded via HTTP often write runtime data relative to their binary. To isolate per-server state from the shared game install directory, generate a per-server config file that redirects the write path to the server's profiles directory, and pass it via a CLI flag (e.g., `--config`). See `FactorioHandler::generateConfigIni()` for the reference implementation.

### SupportsWorkshopMods (capability interface)

Implement `App\Contracts\SupportsWorkshopMods` for games that use Steam Workshop mods downloaded via SteamCMD. Use with the `WorkshopModBehavior` trait for standard implementations.

| Method                                | Purpose                                              |
| ------------------------------------- | ---------------------------------------------------- |
| `requiresLowercaseConversion(): bool` | Whether mod files need lowercase conversion (Linux). |

**Usage pattern:**

```php
use App\Concerns\WorkshopModBehavior;
use App\Contracts\SupportsWorkshopMods;

final class SquadHandler extends AbstractGameHandler implements SteamGameHandler, SupportsWorkshopMods
{
    use WorkshopModBehavior;

    // WorkshopModBehavior provides default implementations for:
    // - requiresLowercaseConversion() → returns false
    // - modSections() → returns single 'Workshop Mods' section
    // - syncPresetMods() → syncs mods() pivot relationship
    // - getPresetModCount() → counts mods() relationship

    // Override requiresLowercaseConversion() to return true if needed (Arma 3, DayZ):
    // public function requiresLowercaseConversion(): bool { return true; }
}
```

**Callers use `instanceof` checks**, not boolean methods:

```php
// Correct — capability interface check
if ($handler instanceof SupportsWorkshopMods) {
    $handler->requiresLowercaseConversion();
}

// Wrong — removed from the interface
// $handler->supportsWorkshopMods()
```

The `WorkshopModBehavior` trait overrides the `modSections()`, `syncPresetMods()`, and `getPresetModCount()` defaults from `AbstractGameHandler` with working workshop mod implementations. These methods stay on the `GameHandler` interface (and `AbstractGameHandler` provides no-op defaults) because `ModPresetController` calls them unconditionally on all game types.

### SupportsRegisteredMods (optional)

Implement `App\Contracts\SupportsRegisteredMods` for games with GUID-based mods (not Steam Workshop). Requires its own mod model, pivot table, and CRUD logic. Override `modSections()`, `syncPresetMods()`, and `getPresetModCount()` directly (no trait — each registered mod system is different).

### SupportsScenarios (optional)

Implement `App\Contracts\SupportsScenarios` for games with discoverable scenarios/missions.

### Other Capability Interfaces

| Interface                 | Purpose                                       | Trait                        |
| ------------------------- | --------------------------------------------- | ---------------------------- |
| `DetectsServerState`      | Boot detection string, auto-restart           | `DetectsServerStateBehavior` |
| `HasQueryPort`            | Game uses a separate query port               | —                            |
| `ManagesModAssets`        | Mod symlink management                        | —                            |
| `SupportsBackups`         | Server profile backup/restore                 | —                            |
| `SupportsHeadlessClients` | Dynamic headless clients (Arma 3 only)        | —                            |
| `SupportsMissions`        | PBO file mission support                      | —                            |
| `WritesNativeLogs`        | Server writes its own log (vs stdout capture) | —                            |

## Config File Generation

Handlers generate server config files via the `generateConfigFiles(Server $server)` method. Two renderers are available:

### TwigConfigRenderer — For text-based configs

Use for key-value config formats (`.cfg`, `.ini`, custom text formats). Templates live in `resources/templates/configs/{game}/`.

```php
use App\Services\Renderer\TwigConfigRenderer;

final class SquadHandler extends AbstractGameHandler implements SteamGameHandler
{
    public function __construct(
        protected TwigConfigRenderer $configRenderer,
    ) {
        parent::__construct(
            value: 'squad',
            label: 'Squad',
            defaultPort: 7787,
            defaultQueryPort: 27165,
            branches: ['public'],
        );
    }

    public function generateConfigFiles(Server $server): void
    {
        $content = $this->configRenderer->render('squad/server.cfg.twig', [
            'hostname' => $server->name,
            'max_players' => $server->max_players,
            'password' => $server->password ?? '',
        ]);

        file_put_contents(
            $server->getProfilesPath() . '/Server.cfg',
            $content,
        );
    }
}
```

Twig template example (`resources/templates/configs/squad/server.cfg.twig`):

```twig
ServerName="{{ hostname }}"
MaxPlayers={{ max_players }}
ServerPassword="{{ password }}"
```

Twig features available:

- Variable interpolation: `{{ variable }}`
- Conditionals: `{% if condition %}...{% endif %}`
- Loops: `{% for item in items %}...{% endfor %}`
- Custom filter: `{{ value|format_decimal }}` (formats floats, strips trailing zeros)
- Autoescape is **disabled** (config files are not HTML)

### JsonConfigRenderer — For JSON configs

Use for games with JSON-based server configuration (e.g., Arma Reforger). Build the config as a PHP array and the renderer handles encoding.

```php
use App\Services\Renderer\JsonConfigRenderer;

final class ReforgerHandler extends AbstractGameHandler implements SteamGameHandler
{
    public function __construct(
        protected JsonConfigRenderer $configRenderer,
    ) {
        parent::__construct(
            value: 'reforger',
            label: 'Arma Reforger',
            defaultPort: 2001,
            defaultQueryPort: 17777,
            branches: ['public', 'profiling'],
            settingsModelClass: ReforgerSettings::class,
            settingsRelationName: 'reforgerSettings',
        );
    }

    public function generateConfigFiles(Server $server): void
    {
        $config = [
            'bindAddress' => '0.0.0.0',
            'bindPort' => $server->port,
            'game' => [
                'name' => $server->name,
                'maxPlayers' => $server->max_players,
            ],
        ];

        file_put_contents(
            $server->getProfilesPath() . '/config.json',
            $this->configRenderer->render('unused', $config),
        );
    }
}
```

The `JsonConfigRenderer` ignores the template parameter — it just encodes the context array as pretty-printed JSON.

## Settings Schema (UI)

The `settingsSchema()` method defines the server settings UI. The frontend renders these dynamically — no React code needed per game.

### Field Types

| Type        | Description            | Key Properties                                            |
| ----------- | ---------------------- | --------------------------------------------------------- |
| `toggle`    | Boolean switch         | `key`, `label`, `default`, `source`                       |
| `number`    | Numeric input          | `key`, `label`, `min`, `max`, `step`, `default`, `source` |
| `text`      | Text input             | `key`, `label`, `placeholder`, `inputMode`, `source`      |
| `textarea`  | Multi-line text        | `key`, `label`, `rows`, `source`                          |
| `segmented` | Toggle group           | `key`, `label`, `options`, `default`, `source`            |
| `separator` | Visual divider         | (no key/label needed)                                     |
| `custom`    | Custom React component | `component` (registered in `custom-components.ts`)        |

### Important Properties

- `source` — Dot-notation path to read the value from server data (e.g., `'arma3Settings.ai_level'`). Matches the relationship name registered by the handler.
- `key` — The form field name sent to the backend on save.
- `showOnCreate` — Show this section in the create server dialog.
- `collapsible` — Section can be collapsed.
- `advanced` — Section is collapsed by default.

### Example

```php
public function settingsSchema(): array
{
    return [
        [
            'title' => 'Server Settings',
            'showOnCreate' => true,
            'fields' => [
                ['key' => 'max_players', 'label' => 'Max Players', 'type' => 'number', 'min' => 1, 'max' => 100, 'default' => 40],
                ['key' => 'password', 'label' => 'Server Password', 'type' => 'text', 'placeholder' => 'Leave empty for no password'],
                ['key' => 'battle_eye', 'label' => 'BattlEye Anti-Cheat', 'type' => 'toggle', 'default' => true],
            ],
        ],
        [
            'title' => 'Advanced Settings',
            'collapsible' => true,
            'advanced' => true,
            'source' => 'squadSettings',
            'fields' => [
                ['key' => 'tick_rate', 'label' => 'Tick Rate', 'type' => 'number', 'min' => 10, 'max' => 60, 'default' => 30],
                ['key' => 'vehicle_claim_time', 'label' => 'Vehicle Claim Time (s)', 'type' => 'number', 'min' => 0, 'max' => 600, 'default' => 300],
            ],
        ],
    ];
}
```

### Custom Components

For game-specific UI that can't be expressed as simple fields (e.g., Reforger's scenario picker), use type `'custom'` with a `component` key. Register the React component in `resources/js/components/servers/custom-components.ts`:

```typescript
import { registerCustomComponent } from '@/components/servers/server-edit-panel';
registerCustomComponent(
    'ReforgerScenarioPicker',
    lazy(() => import('./reforger-scenario-picker')),
);
```

Then reference it in the schema:

```php
['type' => 'custom', 'component' => 'ReforgerScenarioPicker'],
```

## Mod Sections

The `modSections()` method declares what mod tabs appear on the mods and presets pages:

```php
public function modSections(): array
{
    return [
        [
            'type' => 'workshop',           // 'workshop' or 'registered'
            'label' => 'Workshop Mods',      // Tab label
            'relationship' => 'mods',        // Relationship name on ModPreset
            'formField' => 'mod_ids',        // Backend validation field name
        ],
    ];
}
```

The `formField` key must exactly match the field name expected by the backend validation rules. Do not rely on automatic name derivation.

For handlers implementing `SupportsWorkshopMods` with the `WorkshopModBehavior` trait, this is provided automatically. Only override if you need custom behavior (e.g., Reforger's registered mods).

## Existing Handlers (Reference)

| Handler                 | Game            | Extends               | Implements                                                                                                                                                             | Uses Traits                                         |
| ----------------------- | --------------- | --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| `DayZHandler`           | DayZ            | `AbstractGameHandler` | `SteamGameHandler`, `SupportsWorkshopMods`                                                                                                                             | `WorkshopModBehavior`                               |
| `ReforgerHandler`       | Arma Reforger   | `AbstractGameHandler` | `SteamGameHandler`, `DetectsServerState`, `HasQueryPort`, `SupportsRegisteredMods`, `SupportsScenarios`, `WritesNativeLogs`                                            | `DetectsServerStateBehavior`                        |
| `FactorioHandler`       | Factorio        | `AbstractGameHandler` | `DownloadsDirectly`, `DetectsServerState`, `HasQueryPort`                                                                                                              | `DetectsServerStateBehavior`                        |
| `ProjectZomboidHandler` | Project Zomboid | `AbstractGameHandler` | `SteamGameHandler`, `DetectsServerState`, `HasQueryPort`, `SupportsWorkshopMods`                                                                                       | `DetectsServerStateBehavior`, `WorkshopModBehavior` |
| `Arma3Handler`          | Arma 3          | `AbstractGameHandler` | `SteamGameHandler`, `DetectsServerState`, `HasQueryPort`, `ManagesModAssets`, `SupportsBackups`, `SupportsHeadlessClients`, `SupportsMissions`, `SupportsWorkshopMods` | `DetectsServerStateBehavior`, `WorkshopModBehavior` |

**Best reference for:**

| Use Case                                          | Reference Handler       |
| ------------------------------------------------- | ----------------------- |
| Minimal scaffold / WIP (`#[Beta]`)                | `DayZHandler`           |
| JSON config, registered mods, custom component    | `ReforgerHandler`       |
| HTTP download (`DownloadsDirectly`), non-Steam    | `FactorioHandler`       |
| Twig INI templates, wrapper-script games          | `ProjectZomboidHandler` |
| Complex settings, headless clients, full-featured | `Arma3Handler`          |

## Post-Creation Checklist

After scaffolding a handler:

1. Set Steam App IDs (look up on SteamDB) — or implement `DownloadsDirectly` for non-Steam games
2. Verify the server binary is a direct executable vs. a wrapper script (check if it's a shell script that spawns another process)
3. Check if the server requires interactive input on first run (e.g., admin password prompts) and handle via launch flags
4. Implement `buildLaunchCommand()` and `getBinaryPath()` based on the game's docs
5. Create config templates in `resources/templates/configs/{game}/`
6. Define `settingsSchema()` for the server settings UI
7. Fill in `settingsValidationRules()` matching the schema fields
8. If the game supports Steam Workshop mods, implement `SupportsWorkshopMods` and use `WorkshopModBehavior` trait
9. If the game needs lowercase mod conversion (Linux), override `requiresLowercaseConversion()` to return `true`
10. Run `php artisan game:generate-types` to update TypeScript types
11. If settings model was generated, add columns to the migration and run `php artisan migrate`
12. Add a `create{Game}Server()` method to `tests/Concerns/CreatesGameScenarios.php`
13. Handler capability tests in `HandlerCapabilitiesTest.php` are fully dynamic and auto-discover new handlers — no test updates needed
14. Run `vendor/bin/pint --dirty --format agent`
15. Run `vendor/bin/phpstan analyse --memory-limit=512M`
16. Run `php artisan test --compact`

## Common Pitfalls

- Forgetting to add `@phpstan-ignore return.unusedType` on `settingsModelClass()` and `settingsRelationName()` when they return non-null but the interface declares `?string`
- Using `formField` values that don't match backend validation rule keys (causes silent form submission failures)
- Forgetting to run `game:generate-types` after changing a handler (TypeScript types will be stale)
- Not researching the actual game server configuration format before writing templates
- **Wrapper-script servers** — Some games (e.g., Project Zomboid) use a shell script that spawns a child process (Java, Mono, etc.). `ServerProcessService::killProcessTree()` handles this by recursively killing children via `pgrep -P`, but the Docker container must have the `procps` package installed for `pgrep` to be available
- **Interactive stdin prompts** — Some servers prompt for input on first run (e.g., PZ admin password). Since the process runs detached with `/dev/null` as stdin, this causes a crash. Pass required values as launch flags instead (e.g., `-adminpassword`)
- **Config auto-expansion** — Some games (e.g., Project Zomboid) auto-expand partial config files with all default values on first boot. The Twig template only needs to set managed values; the game fills in the rest. Don't try to template every possible setting
- **Invalid launch flags** — Not all parameters documented online are valid launch flags. Some settings (like IP binding in PZ) must be set in the config file, not on the command line. Test launch commands against actual server behavior
- **Partial JSON config sections** — Some games (e.g., Factorio 2.0) require ALL sub-keys when a JSON section is present — they do not merge with built-in defaults. If you provide `"pollution": {"enabled": true}` but omit `diffusion_ratio` etc., the server crashes. Either include all sub-keys with their defaults or omit the section entirely. Check the game's example config files (e.g., `data/map-settings.example.json`) for the full required structure. This applies to ALL top-level sections too — if the game expects `steering`, `path_finder`, `unit_group`, etc., they must all be present
- **Write-data isolation** — Games that use a single `write-data` directory (e.g., Factorio) will write saves, logs, and player data to the game install directory by default. Multiple servers sharing the same install will conflict. Generate a per-server config that redirects write-data to the server's profiles path
- **Save file location** — When using per-server write-data paths, save files end up in the profiles directory, not the game install directory. Use explicit save path flags (e.g., `--start-server path/to/save.zip`) instead of relative flags like `--start-server-load-latest` which search the write-data directory
- **Coupled CLI flags** — Some flags require companion flags (e.g., Factorio's `--rcon-port` requires `--rcon-password`). Only include the full set when all values are configured, otherwise the server crashes
