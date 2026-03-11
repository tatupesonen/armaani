<?php

namespace Tests\Feature\Mods;

use App\Models\ModPreset;
use App\Models\ReforgerMod;
use Tests\TestCase;

class ReforgerModManagementTest extends TestCase
{
    // ---------------------------------------------------------------
    // Store
    // ---------------------------------------------------------------

    public function test_user_can_add_reforger_mod(): void
    {
        $this->post(route('registered-mods.store', ['gameType' => 'reforger']), [
            'mod_id' => 'ABC123DEF456',
            'name' => 'Test Reforger Mod',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reforger_mods', [
            'mod_id' => 'ABC123DEF456',
            'name' => 'Test Reforger Mod',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->asGuest()->post(route('registered-mods.store', ['gameType' => 'reforger']), [
            'mod_id' => 'ABC123',
            'name' => 'Test',
        ])->assertRedirect(route('login'));
    }

    public function test_store_validates_mod_id_required(): void
    {
        $this->post(route('registered-mods.store', ['gameType' => 'reforger']), [
            'mod_id' => '',
            'name' => 'Test Mod',
        ])
            ->assertSessionHasErrors(['mod_id']);
    }

    public function test_store_validates_name_required(): void
    {
        $this->post(route('registered-mods.store', ['gameType' => 'reforger']), [
            'mod_id' => 'ABC123',
            'name' => '',
        ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_mod_id_unique(): void
    {
        ReforgerMod::factory()->create(['mod_id' => 'DUPLICATE123']);

        $this->post(route('registered-mods.store', ['gameType' => 'reforger']), [
            'mod_id' => 'DUPLICATE123',
            'name' => 'Another Mod',
        ])
            ->assertSessionHasErrors(['mod_id']);

        $this->assertDatabaseCount('reforger_mods', 1);
    }

    // ---------------------------------------------------------------
    // Destroy
    // ---------------------------------------------------------------

    public function test_user_can_delete_reforger_mod(): void
    {
        $mod = ReforgerMod::factory()->create();

        $this->delete(route('registered-mods.destroy', ['gameType' => 'reforger', 'modId' => $mod->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('reforger_mods', ['id' => $mod->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $mod = ReforgerMod::factory()->create();

        $this->asGuest()->delete(route('registered-mods.destroy', ['gameType' => 'reforger', 'modId' => $mod->id]))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('reforger_mods', ['id' => $mod->id]);
    }

    public function test_destroy_detaches_from_presets(): void
    {
        $mod = ReforgerMod::factory()->create();
        $preset = ModPreset::factory()->create(['game_type' => 'reforger']);
        $preset->reforgerMods()->attach($mod);

        $this->assertDatabaseHas('mod_preset_reforger_mod', ['reforger_mod_id' => $mod->id]);

        $this->delete(route('registered-mods.destroy', ['gameType' => 'reforger', 'modId' => $mod->id]));

        $this->assertDatabaseMissing('reforger_mods', ['id' => $mod->id]);
        $this->assertDatabaseMissing('mod_preset_reforger_mod', ['reforger_mod_id' => $mod->id]);
    }
}
