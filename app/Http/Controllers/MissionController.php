<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mission\StoreMissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('missions/index', [
            'missions' => $this->listMissions(),
        ]);
    }

    public function store(StoreMissionRequest $request): RedirectResponse
    {
        $path = config('arma.missions_base_path');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $count = 0;
        foreach ($request->file('missions') as $file) {
            if (! str_ends_with(strtolower($file->getClientOriginalName()), '.pbo')) {
                continue;
            }
            $file->move($path, $file->getClientOriginalName());
            $count++;
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") uploaded {$count} mission(s)");

        return back()->with('success', "{$count} mission file(s) uploaded.");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = config('arma.missions_base_path').'/'.$filename;

        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $path = config('arma.missions_base_path').'/'.$filename;

        if (file_exists($path)) {
            unlink($path);
            Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted mission: {$filename}");
        }

        return back()->with('success', 'Mission file deleted.');
    }

    /**
     * @return array<int, array{name: string, size: int, modified_at: string}>
     */
    private function listMissions(): array
    {
        $path = config('arma.missions_base_path');
        if (! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*.pbo') ?: [];

        return collect($files)
            ->map(fn (string $file) => [
                'name' => basename($file),
                'size' => filesize($file),
                'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
            ])
            ->sortByDesc('modified_at')
            ->values()
            ->toArray();
    }
}
