<?php

namespace App\Http\Controllers;

use App\Contracts\GameHandler;
use App\GameManager;
use App\Http\Requests\ModPreset\ImportModPresetRequest;
use App\Http\Requests\ModPreset\StoreModPresetRequest;
use App\Http\Requests\ModPreset\UpdateModPresetRequest;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use App\Models\WorkshopMod;
use App\Services\Mod\PresetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ModPresetController extends Controller
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

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
            'gameTypes' => collect($this->gameManager->allHandlers())->map(fn (GameHandler $handler) => [
                'value' => $handler->value(),
                'label' => $handler->label(),
                'supportsWorkshopMods' => $handler->supportsWorkshopMods(),
            ])->values(),
            'workshopMods' => WorkshopMod::query()->orderBy('name')->get(),
            'reforgerMods' => ReforgerMod::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreModPresetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $preset = ModPreset::query()->create([
            'game_type' => $validated['game_type'],
            'name' => $validated['name'],
        ]);

        if ($validated['game_type'] === 'reforger') {
            $preset->reforgerMods()->sync($validated['reforger_mod_ids'] ?? []);
        } else {
            $preset->mods()->sync($validated['mod_ids'] ?? []);
        }

        Log::info(auth_context()." created preset: {$preset->name}");

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

    public function update(UpdateModPresetRequest $request, ModPreset $modPreset): RedirectResponse
    {
        $validated = $request->validated();
        $gameType = $modPreset->game_type;

        $modPreset->update(['name' => $validated['name']]);

        if ($modPreset->game_type === 'reforger') {
            $modPreset->reforgerMods()->sync($validated['reforger_mod_ids'] ?? []);
        } else {
            $modPreset->mods()->sync($validated['mod_ids'] ?? []);
        }

        Log::info(auth_context()." updated preset: {$modPreset->name}");

        return to_route('presets.index')->with('success', "Preset '{$modPreset->name}' updated.");
    }

    public function destroy(ModPreset $modPreset): RedirectResponse
    {
        Log::info(auth_context()." deleted preset: {$modPreset->name}");

        $modPreset->delete();

        return back()->with('success', 'Preset deleted.');
    }

    public function import(ImportModPresetRequest $request, PresetImportService $importService): RedirectResponse
    {
        $html = file_get_contents($request->file('import_file')->getRealPath());

        try {
            $preset = $importService->importFromHtml($html, $request->input('import_name'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        }

        $modCount = $preset->mods()->count();

        Log::info(auth_context()." imported preset: {$preset->name}");

        return back()->with('success', "Preset '{$preset->name}' imported with {$modCount} mod(s).");
    }
}
