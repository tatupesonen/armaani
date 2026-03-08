<?php

namespace App\Http\Controllers;

use App\Models\ReforgerMod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReforgerModController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mod_id' => ['required', 'string', 'unique:reforger_mods,mod_id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

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
