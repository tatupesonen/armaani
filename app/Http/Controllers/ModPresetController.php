<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use App\Models\WorkshopMod;
use App\Services\PresetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ModPresetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('presets/index', [
            'presets' => ModPreset::query()
                ->withCount(['mods', 'reforgerMods'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('presets/create', [
            'gameTypes' => collect(GameType::cases())->map(fn (GameType $gt) => [
                'value' => $gt->value,
                'label' => $gt->label(),
                'supportsWorkshopMods' => $gt->supportsWorkshopMods(),
            ]),
            'workshopMods' => WorkshopMod::query()->orderBy('name')->get(),
            'reforgerMods' => ReforgerMod::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $gameType = GameType::from($request->input('game_type', 'arma3'));

        $validated = $request->validate([
            'game_type' => ['required', Rule::enum(GameType::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique('mod_presets')->where('game_type', $gameType->value)],
            'mod_ids' => ['nullable', 'array'],
            'mod_ids.*' => ['integer', 'exists:workshop_mods,id'],
            'reforger_mod_ids' => ['nullable', 'array'],
            'reforger_mod_ids.*' => ['integer', 'exists:reforger_mods,id'],
        ]);

        $preset = ModPreset::query()->create([
            'game_type' => $validated['game_type'],
            'name' => $validated['name'],
        ]);

        if ($gameType === GameType::ArmaReforger) {
            $preset->reforgerMods()->sync($validated['reforger_mod_ids'] ?? []);
        } else {
            $preset->mods()->sync($validated['mod_ids'] ?? []);
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") created preset: {$preset->name}");

        return to_route('presets.index')->with('success', "Preset '{$preset->name}' created.");
    }

    public function edit(ModPreset $modPreset): Response
    {
        $modPreset->load(['mods', 'reforgerMods']);

        return Inertia::render('presets/edit', [
            'preset' => $modPreset,
            'workshopMods' => WorkshopMod::query()
                ->forGame($modPreset->game_type)
                ->orderBy('name')
                ->get(),
            'reforgerMods' => ReforgerMod::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ModPreset $modPreset): RedirectResponse
    {
        $gameType = $modPreset->game_type;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('mod_presets')->where('game_type', $gameType->value)->ignore($modPreset->id)],
            'mod_ids' => ['nullable', 'array'],
            'mod_ids.*' => ['integer', 'exists:workshop_mods,id'],
            'reforger_mod_ids' => ['nullable', 'array'],
            'reforger_mod_ids.*' => ['integer', 'exists:reforger_mods,id'],
        ]);

        $modPreset->update(['name' => $validated['name']]);

        if ($gameType === GameType::ArmaReforger) {
            $modPreset->reforgerMods()->sync($validated['reforger_mod_ids'] ?? []);
        } else {
            $modPreset->mods()->sync($validated['mod_ids'] ?? []);
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") updated preset: {$modPreset->name}");

        return to_route('presets.index')->with('success', "Preset '{$modPreset->name}' updated.");
    }

    public function destroy(ModPreset $modPreset): RedirectResponse
    {
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted preset: {$modPreset->name}");

        $modPreset->delete();

        return back()->with('success', 'Preset deleted.');
    }

    public function import(Request $request, PresetImportService $importService): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'max:2048'],
            'import_name' => ['nullable', 'string', 'max:255'],
        ]);

        $html = file_get_contents($request->file('import_file')->getRealPath());

        try {
            $preset = $importService->importFromHtml($html, $request->input('import_name'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        }

        $modCount = $preset->mods()->count();

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") imported preset: {$preset->name}");

        return back()->with('success', "Preset '{$preset->name}' imported with {$modCount} mod(s).");
    }
}
