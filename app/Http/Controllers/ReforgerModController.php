<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReforgerMod\StoreReforgerModRequest;
use App\Models\ReforgerMod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ReforgerModController extends Controller
{
    public function store(StoreReforgerModRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $mod = ReforgerMod::query()->create($validated);

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") added Reforger mod: {$mod->name}");

        return back()->with('success', "Reforger mod '{$mod->name}' added.");
    }

    public function destroy(ReforgerMod $reforgerMod): RedirectResponse
    {
        $reforgerMod->presets()->detach();

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted Reforger mod: {$reforgerMod->name}");

        $reforgerMod->delete();

        return back()->with('success', 'Reforger mod deleted.');
    }
}
