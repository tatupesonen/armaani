<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameInstallController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\ModPresetController;
use App\Http\Controllers\RegisteredModController;
use App\Http\Controllers\ServerBackupController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SteamSettingsController;
use App\Http\Controllers\WorkshopModController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Game Installs
    Route::get('game-installs', [GameInstallController::class, 'index'])->name('game-installs.index');
    Route::post('game-installs', [GameInstallController::class, 'store'])->name('game-installs.store');
    Route::post('game-installs/{gameInstall}/reinstall', [GameInstallController::class, 'reinstall'])->name('game-installs.reinstall');
    Route::delete('game-installs/{gameInstall}', [GameInstallController::class, 'destroy'])->name('game-installs.destroy');

    // Servers
    Route::get('servers', [ServerController::class, 'index'])->name('servers.index');
    Route::post('servers', [ServerController::class, 'store'])->name('servers.store');
    Route::put('servers/{server}', [ServerController::class, 'update'])->name('servers.update');
    Route::delete('servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');
    Route::post('servers/{server}/start', [ServerController::class, 'start'])->name('servers.start');
    Route::post('servers/{server}/stop', [ServerController::class, 'stop'])->name('servers.stop');
    Route::post('servers/{server}/restart', [ServerController::class, 'restart'])->name('servers.restart');
    Route::post('servers/{server}/headless-client/add', [ServerController::class, 'addHeadlessClient'])->name('servers.headless-client.add');
    Route::post('servers/{server}/headless-client/remove', [ServerController::class, 'removeHeadlessClient'])->name('servers.headless-client.remove');
    Route::get('servers/{server}/launch-command', [ServerController::class, 'launchCommand'])->name('servers.launch-command');
    Route::get('servers/{server}/log', [ServerController::class, 'serverLog'])->name('servers.log');
    Route::get('servers/{server}/status', [ServerController::class, 'status'])->name('servers.status');
    Route::get('servers/{server}/scenarios', [ServerController::class, 'scenarios'])->name('servers.scenarios');
    Route::post('servers/{server}/scenarios/reload', [ServerController::class, 'reloadScenarios'])->name('servers.scenarios.reload');

    // Server Backups
    Route::post('servers/{server}/backups', [ServerBackupController::class, 'store'])->name('servers.backups.store');
    Route::post('servers/{server}/backups/upload', [ServerBackupController::class, 'upload'])->name('servers.backups.upload');
    Route::post('servers/backups/{serverBackup}/restore', [ServerBackupController::class, 'restore'])->name('servers.backups.restore');
    Route::get('servers/backups/{serverBackup}/download', [ServerBackupController::class, 'download'])->name('servers.backups.download');
    Route::delete('servers/backups/{serverBackup}', [ServerBackupController::class, 'destroy'])->name('servers.backups.destroy');

    // Mods
    Route::get('mods', [WorkshopModController::class, 'index'])->name('mods.index');
    Route::post('mods', [WorkshopModController::class, 'store'])->name('mods.store');
    Route::post('mods/{workshopMod}/retry', [WorkshopModController::class, 'retry'])->name('mods.retry');
    Route::post('mods/retry-all-failed', [WorkshopModController::class, 'retryAllFailed'])->name('mods.retry-all-failed');
    Route::delete('mods/{workshopMod}', [WorkshopModController::class, 'destroy'])->name('mods.destroy');
    Route::post('mods/update-selected', [WorkshopModController::class, 'updateSelected'])->name('mods.update-selected');
    Route::post('mods/check-for-updates', [WorkshopModController::class, 'checkForUpdates'])->name('mods.check-for-updates');
    Route::post('mods/update-all-outdated', [WorkshopModController::class, 'updateAllOutdated'])->name('mods.update-all-outdated');

    // Registered Mods (game-generic — e.g., Reforger GUID mods)
    Route::post('registered-mods/{gameType}', [RegisteredModController::class, 'store'])->name('registered-mods.store');
    Route::delete('registered-mods/{gameType}/{modId}', [RegisteredModController::class, 'destroy'])->name('registered-mods.destroy');

    // Missions
    Route::get('missions', [MissionController::class, 'index'])->name('missions.index');
    Route::post('missions', [MissionController::class, 'store'])->name('missions.store');
    Route::get('missions/{filename}/download', [MissionController::class, 'download'])->name('missions.download');
    Route::delete('missions/{filename}', [MissionController::class, 'destroy'])->name('missions.destroy');

    // Presets
    Route::get('presets', [ModPresetController::class, 'index'])->name('presets.index');
    Route::get('presets/create', [ModPresetController::class, 'create'])->name('presets.create');
    Route::post('presets', [ModPresetController::class, 'store'])->name('presets.store');
    Route::get('presets/{modPreset}/edit', [ModPresetController::class, 'edit'])->name('presets.edit');
    Route::put('presets/{modPreset}', [ModPresetController::class, 'update'])->name('presets.update');
    Route::delete('presets/{modPreset}', [ModPresetController::class, 'destroy'])->name('presets.destroy');
    Route::post('presets/import', [ModPresetController::class, 'import'])->name('presets.import');

    // Steam Settings
    Route::get('steam-settings', [SteamSettingsController::class, 'index'])->name('steam-settings');
    Route::post('steam-settings/credentials', [SteamSettingsController::class, 'saveCredentials'])->name('steam-settings.credentials');
    Route::post('steam-settings/api-key', [SteamSettingsController::class, 'saveApiKey'])->name('steam-settings.api-key');
    Route::post('steam-settings/settings', [SteamSettingsController::class, 'saveSettings'])->name('steam-settings.settings');
    Route::post('steam-settings/verify-login', [SteamSettingsController::class, 'verifyLogin'])->name('steam-settings.verify-login');
    Route::post('steam-settings/verify-api-key', [SteamSettingsController::class, 'verifyApiKey'])->name('steam-settings.verify-api-key');
    Route::post('steam-settings/discord-webhook', [SteamSettingsController::class, 'saveDiscordWebhook'])->name('steam-settings.discord-webhook');
    Route::post('steam-settings/test-discord-webhook', [SteamSettingsController::class, 'testDiscordWebhook'])->name('steam-settings.test-discord-webhook');
});

require __DIR__.'/settings.php';
