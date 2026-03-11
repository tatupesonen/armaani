<?php

namespace Tests\Feature\GameHandlers;

use App\Contracts\DetectsServerState;
use App\Contracts\DownloadsDirectly;
use App\Contracts\GameHandler;
use App\Contracts\SteamGameHandler;
use App\Contracts\SupportsRegisteredMods;
use App\Contracts\SupportsWorkshopMods;
use App\Contracts\WritesNativeLogs;
use App\GameHandlers\AbstractGameHandler;
use App\GameManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dynamic capability tests for all game handlers.
 *
 * These tests discover handlers at runtime via GameManager — no hardcoded
 * handler names, counts, or array positions. Adding a new game handler
 * automatically includes it in all tests.
 */
class HandlerCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private GameManager $manager;

    /** @var array<string, GameHandler> */
    private array $handlers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(GameManager::class);
        $this->handlers = $this->manager->allHandlers();
    }

    // ---------------------------------------------------------------
    // Core contract: every handler implements GameHandler
    // ---------------------------------------------------------------

    public function test_all_handlers_implement_game_handler_interface(): void
    {
        $this->assertNotEmpty($this->handlers, 'GameManager should discover at least one handler');

        foreach ($this->handlers as $driver => $handler) {
            $this->assertInstanceOf(GameHandler::class, $handler, "{$driver} must implement GameHandler");
        }
    }

    public function test_all_handlers_extend_abstract_game_handler(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $this->assertInstanceOf(AbstractGameHandler::class, $handler, "{$driver} must extend AbstractGameHandler");
        }
    }

    public function test_driver_key_matches_handler_value(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $this->assertSame($driver, $handler->value(), "{$driver}: driver key must match value()");
        }
    }

    // ---------------------------------------------------------------
    // Identity properties: non-empty, valid ranges
    // ---------------------------------------------------------------

    public function test_all_handlers_have_non_empty_identity(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $this->assertNotEmpty($handler->value(), "{$driver}: value() must not be empty");
            $this->assertNotEmpty($handler->label(), "{$driver}: label() must not be empty");
        }
    }

    public function test_all_handlers_have_valid_ports(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $this->assertGreaterThan(0, $handler->defaultPort(), "{$driver}: defaultPort() must be > 0");
            $this->assertLessThanOrEqual(65535, $handler->defaultPort(), "{$driver}: defaultPort() must be <= 65535");
            $this->assertGreaterThan(0, $handler->defaultQueryPort(), "{$driver}: defaultQueryPort() must be > 0");
            $this->assertLessThanOrEqual(65535, $handler->defaultQueryPort(), "{$driver}: defaultQueryPort() must be <= 65535");
        }
    }

    public function test_all_handlers_have_at_least_one_branch(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $this->assertNotEmpty($handler->branches(), "{$driver}: branches() must not be empty");
        }
    }

    public function test_handler_values_are_unique(): void
    {
        $values = array_map(fn (GameHandler $h) => $h->value(), $this->handlers);

        $this->assertSame(
            count($values),
            count(array_unique($values)),
            'Handler values must be unique across all handlers',
        );
    }

    // ---------------------------------------------------------------
    // Installation: every handler must support exactly one strategy
    // ---------------------------------------------------------------

    public function test_every_handler_has_exactly_one_installation_strategy(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $isSteam = $handler instanceof SteamGameHandler;
            $isHttp = $handler instanceof DownloadsDirectly;

            $this->assertTrue(
                $isSteam xor $isHttp,
                "{$driver}: must implement exactly one of SteamGameHandler or DownloadsDirectly (steam={$isSteam}, http={$isHttp})",
            );
        }
    }

    // ---------------------------------------------------------------
    // Settings model consistency
    // ---------------------------------------------------------------

    public function test_settings_model_class_and_relation_name_are_paired(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $hasModel = $handler->settingsModelClass() !== null;
            $hasRelation = $handler->settingsRelationName() !== null;

            $this->assertSame(
                $hasModel,
                $hasRelation,
                "{$driver}: settingsModelClass and settingsRelationName must both be set or both be null",
            );
        }
    }

    // ---------------------------------------------------------------
    // Capability interface contracts
    // ---------------------------------------------------------------

    public function test_detects_server_state_handlers_return_valid_boot_strings(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if (! $handler instanceof DetectsServerState) {
                continue;
            }

            $this->assertNotEmpty(
                $handler->getBootDetectionStrings(),
                "{$driver}: DetectsServerState handler must have at least one boot detection string",
            );
        }
    }

    public function test_supports_workshop_mods_handlers_have_mod_sections(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if (! $handler instanceof SupportsWorkshopMods) {
                continue;
            }

            $sections = $handler->modSections();
            $this->assertNotEmpty($sections, "{$driver}: SupportsWorkshopMods handler must return at least one mod section");

            $hasWorkshopSection = false;
            foreach ($sections as $section) {
                if ($section['type'] === 'workshop') {
                    $hasWorkshopSection = true;
                }
            }
            $this->assertTrue($hasWorkshopSection, "{$driver}: SupportsWorkshopMods handler must include a 'workshop' mod section");
        }
    }

    public function test_non_workshop_handlers_without_overrides_return_empty_mod_sections(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if ($handler instanceof SupportsWorkshopMods) {
                continue;
            }

            if ($handler instanceof SupportsRegisteredMods) {
                // Registered mod handlers override modSections() — skip
                continue;
            }

            $this->assertEmpty(
                $handler->modSections(),
                "{$driver}: handler without mod support should return empty modSections()",
            );
        }
    }

    public function test_writes_native_logs_handlers_provide_valid_patterns(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if (! $handler instanceof WritesNativeLogs) {
                continue;
            }

            $this->assertNotEmpty(
                $handler->getNativeLogFilePattern(),
                "{$driver}: WritesNativeLogs handler must return a non-empty file pattern",
            );
        }
    }

    public function test_supports_registered_mods_handlers_provide_model_config(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if (! $handler instanceof SupportsRegisteredMods) {
                continue;
            }

            $this->assertNotEmpty($handler->registeredModModelClass(), "{$driver}: must provide registeredModModelClass");
            $this->assertNotEmpty($handler->registeredModRelationName(), "{$driver}: must provide registeredModRelationName");
            $this->assertNotEmpty($handler->registeredModPivotTable(), "{$driver}: must provide registeredModPivotTable");
            $this->assertNotEmpty($handler->registeredModValidationRules(), "{$driver}: must provide registeredModValidationRules");
        }
    }

    public function test_steam_game_handlers_have_valid_app_ids(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if (! $handler instanceof SteamGameHandler) {
                continue;
            }

            $this->assertGreaterThan(0, $handler->serverAppId(), "{$driver}: serverAppId must be > 0");
            $this->assertGreaterThan(0, $handler->gameId(), "{$driver}: gameId must be > 0");
            $this->assertGreaterThan(0, $handler->consumerAppId(), "{$driver}: consumerAppId must be > 0");
        }
    }

    public function test_downloads_directly_handlers_have_valid_download_config(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if (! $handler instanceof DownloadsDirectly) {
                continue;
            }

            foreach ($handler->branches() as $branch) {
                $url = $handler->getDownloadUrl($branch);
                $this->assertNotEmpty($url, "{$driver}: getDownloadUrl for branch '{$branch}' must not be empty");
                $this->assertStringStartsWith('https://', $url, "{$driver}: download URL must use HTTPS");
            }

            $this->assertGreaterThanOrEqual(0, $handler->getArchiveStripComponents(), "{$driver}: getArchiveStripComponents must be >= 0");
        }
    }

    // ---------------------------------------------------------------
    // Validation rules: must return arrays
    // ---------------------------------------------------------------

    public function test_all_handlers_return_array_validation_rules(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            $this->assertIsArray($handler->serverValidationRules(), "{$driver}: serverValidationRules must return array");
            $this->assertIsArray($handler->settingsValidationRules(), "{$driver}: settingsValidationRules must return array");
            $this->assertIsArray($handler->settingsSchema(), "{$driver}: settingsSchema must return array");
        }
    }

    // ---------------------------------------------------------------
    // Lowercase conversion: only workshop mod handlers can require it
    // ---------------------------------------------------------------

    public function test_lowercase_conversion_only_on_workshop_mod_handlers(): void
    {
        foreach ($this->handlers as $driver => $handler) {
            if ($handler instanceof SupportsWorkshopMods) {
                $this->assertIsBool($handler->requiresLowercaseConversion(), "{$driver}: requiresLowercaseConversion must return bool");
            }
        }
    }
}
