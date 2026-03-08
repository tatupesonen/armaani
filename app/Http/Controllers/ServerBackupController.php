<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerBackup;
use App\Services\ServerBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerBackupController extends Controller
{
    public function store(Request $request, Server $server, ServerBackupService $backupService): RedirectResponse
    {
        $request->validate([
            'backup_name' => ['nullable', 'string', 'max:255'],
        ]);

        $backup = $backupService->createFromServer($server, $request->input('backup_name'));

        if ($backup === null) {
            return back()->with('error', 'No profile file found to back up.');
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") created backup for server: {$server->name}");

        return back()->with('success', 'Backup created.');
    }

    public function upload(Request $request, Server $server, ServerBackupService $backupService): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'max:10240'],
            'backup_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data = file_get_contents($request->file('backup_file')->getRealPath());

        $backupService->createFromUpload(
            $server,
            $data,
            $request->input('backup_name') ?: $request->file('backup_file')->getClientOriginalName(),
        );

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") uploaded backup for server: {$server->name}");

        return back()->with('success', 'Backup uploaded.');
    }

    public function restore(ServerBackup $serverBackup, ServerBackupService $backupService): RedirectResponse
    {
        $server = $serverBackup->server;

        if ($server->status !== ServerStatus::Stopped) {
            return back()->with('error', 'Server must be stopped before restoring a backup.');
        }

        $backupService->restore($serverBackup);

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") restored backup for server: {$server->name}");

        return back()->with('success', 'Backup restored.');
    }

    public function download(ServerBackup $serverBackup): StreamedResponse
    {
        $server = $serverBackup->server;
        $extension = $server->game_type->profileExtension() ?? '.profile';
        $filename = ($serverBackup->name ?? 'backup-'.$serverBackup->id).$extension;

        return response()->streamDownload(function () use ($serverBackup): void {
            echo $serverBackup->data;
        }, $filename);
    }

    public function destroy(ServerBackup $serverBackup): RedirectResponse
    {
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted backup #{$serverBackup->id}");

        $serverBackup->delete();

        return back()->with('success', 'Backup deleted.');
    }
}
