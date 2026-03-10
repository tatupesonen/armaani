<?php

namespace Tests\Feature;

use Tests\TestCase;

class GenerateGameTypesCommandTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = storage_path('test-generated.d.ts');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outputPath)) {
            unlink($this->outputPath);
        }

        parent::tearDown();
    }

    public function test_command_generates_typescript_file(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts'])
            ->assertSuccessful();

        $this->assertFileExists($this->outputPath);
    }

    public function test_output_contains_auto_generated_header(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        $this->assertStringContainsString('AUTO-GENERATED', $content);
        $this->assertStringContainsString('DO NOT EDIT MANUALLY', $content);
    }

    public function test_output_contains_server_base_type(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        $this->assertStringContainsString('export type ServerBase = {', $content);
        $this->assertStringContainsString('id: number;', $content);
        $this->assertStringContainsString('game_type: string;', $content);
        $this->assertStringContainsString("status: import('./game').ServerStatus;", $content);
    }

    public function test_output_contains_arma3_settings_type(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        $this->assertStringContainsString('export type Arma3Settings = {', $content);
        $this->assertStringContainsString('reduced_damage: boolean;', $content);
        $this->assertStringContainsString('skill_ai: string;', $content);
        $this->assertStringContainsString('max_msg_send: number;', $content);
        $this->assertStringContainsString('terrain_grid: string;', $content);
    }

    public function test_output_contains_reforger_settings_type(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        $this->assertStringContainsString('export type ReforgerSettings = {', $content);
        $this->assertStringContainsString('scenario_id: string | null;', $content);
        $this->assertStringContainsString('third_person_view_enabled: boolean;', $content);
        $this->assertStringContainsString('max_fps: number;', $content);
    }

    public function test_output_contains_discriminated_union_variants(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        // Each variant narrows game_type to a string literal
        $this->assertStringContainsString("game_type: 'arma3';", $content);
        $this->assertStringContainsString("game_type: 'reforger';", $content);
        $this->assertStringContainsString("game_type: 'dayz';", $content);

        // Arma3 variant has settings
        $this->assertStringContainsString('arma3_settings?: Arma3Settings;', $content);

        // Reforger variant has settings
        $this->assertStringContainsString('reforger_settings?: ReforgerSettings;', $content);

        // DayZ has no settings schema, so no settings property
        $this->assertMatchesRegularExpression(
            "/export type DayzServer = ServerBase & \{\s*game_type: 'dayz';\s*\};/",
            $content,
        );
    }

    public function test_output_contains_server_union_type(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        // The final union should include all three variants
        $this->assertMatchesRegularExpression(
            '/export type Server = .*Arma3Server.*DayzServer.*ReforgerServer/',
            $content,
        );
    }

    public function test_store_as_string_fields_generate_string_type(): void
    {
        $this->artisan('game:generate-types', ['--path' => 'storage/test-generated.d.ts']);

        $content = file_get_contents($this->outputPath);

        // skill_ai and precision_ai are number fields with storeAsString => true
        $this->assertStringContainsString('skill_ai: string;', $content);
        $this->assertStringContainsString('precision_ai: string;', $content);

        // min_bandwidth is a text field (always string)
        $this->assertStringContainsString('min_bandwidth: string;', $content);
    }
}
