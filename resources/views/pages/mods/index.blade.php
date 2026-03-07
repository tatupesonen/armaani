<?php

use App\Enums\InstallationStatus;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Livewire\Concerns\AuditsActions;
use App\Models\ReforgerMod;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\SteamWorkshopService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mods')] class extends Component
{
    use AuditsActions;

    public string $activeTab = 'workshop';

    public string $workshopId = '';

    public string $search = '';

    public string $sortBy = '';

    public string $sortDirection = 'asc';

    /** @var list<int> */
    public array $selectedMods = [];

    /** @var array<int, bool> */
    public array $showLogs = [];

    public string $reforgerModId = '';

    public string $reforgerModName = '';

    #[Computed]
    public function installedModStats(): array
    {
        $result = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Installed)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size')
            ->first();

        return [
            'count' => (int) $result->count,
            'total_size' => (int) $result->total_size,
        ];
    }

    #[Computed]
    public function mods()
    {
        $statusOrder = [
            InstallationStatus::Installing->value => 0,
            InstallationStatus::Queued->value => 1,
            InstallationStatus::Failed->value => 2,
            InstallationStatus::Installed->value => 3,
        ];

        $query = WorkshopMod::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('workshop_id', 'like', '%'.$this->search.'%');
                });
            });

        if ($this->sortBy !== '' && $this->sortBy !== 'status') {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->orderByDesc('created_at');
        }

        $mods = $query->get();

        if ($this->sortBy === 'status') {
            return $this->sortDirection === 'asc'
                ? $mods->sortBy(fn (WorkshopMod $mod) => $statusOrder[$mod->installation_status->value] ?? 99)->values()
                : $mods->sortByDesc(fn (WorkshopMod $mod) => $statusOrder[$mod->installation_status->value] ?? 99)->values();
        }

        return $mods;
    }

    public function toggleSort(string $column): void
    {
        if ($this->sortBy === $column) {
            if ($this->sortDirection === 'asc') {
                $this->sortDirection = 'desc';
            } else {
                $this->sortBy = '';
                $this->sortDirection = 'asc';
            }
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        unset($this->mods);
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->mods->contains(fn (WorkshopMod $mod) => $mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued
        );
    }

    #[Computed]
    public function failedMods()
    {
        return $this->mods->filter(fn (WorkshopMod $mod) => $mod->installation_status === InstallationStatus::Failed);
    }

    #[Computed]
    public function outdatedMods()
    {
        return $this->mods->filter(fn (WorkshopMod $mod) => $mod->isOutdated());
    }

    #[Computed]
    public function selectableMods()
    {
        return $this->mods->filter(fn (WorkshopMod $mod) => $mod->installation_status !== InstallationStatus::Installing && $mod->installation_status !== InstallationStatus::Queued
        );
    }

    #[Computed]
    public function isAllSelected(): bool
    {
        return $this->selectableMods->isNotEmpty()
            && $this->selectableMods->every(fn (WorkshopMod $mod) => in_array($mod->id, $this->selectedMods));
    }

    public function toggleSelectAll(): void
    {
        if ($this->isAllSelected) {
            $this->selectedMods = [];
        } else {
            $this->selectedMods = $this->selectableMods->pluck('id')->all();
        }
    }

    public function updateSelected(): void
    {
        $mods = WorkshopMod::query()
            ->whereIn('id', $this->selectedMods)
            ->whereNotIn('installation_status', [InstallationStatus::Installing, InstallationStatus::Queued])
            ->get();

        if ($mods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::query()->latest()->first()?->mod_download_batch_size ?? 5;

        foreach ($mods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Queued, 'progress_pct' => 0]);
        }

        foreach ($mods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                BatchDownloadModsJob::dispatch($batch);
            }
        }

        $this->auditLog('queued update for '.$mods->count().' mods in batches of '.$batchSize);

        $this->selectedMods = [];
        unset($this->mods, $this->installedModStats, $this->failedMods, $this->outdatedMods, $this->selectableMods, $this->isAllSelected);
    }

    public function checkForUpdates(SteamWorkshopService $workshop): void
    {
        $installedMods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Installed)
            ->get();

        if ($installedMods->isEmpty()) {
            return;
        }

        $workshopIds = $installedMods->pluck('workshop_id')->all();
        $detailsMap = $workshop->getMultipleModDetails($workshopIds);

        $updatedCount = 0;

        foreach ($installedMods as $mod) {
            $details = $detailsMap[$mod->workshop_id] ?? null;

            if ($details && isset($details['time_updated'])) {
                $mod->updateQuietly([
                    'steam_updated_at' => \Carbon\Carbon::createFromTimestamp($details['time_updated']),
                ]);
                $updatedCount++;
            }
        }

        $this->auditLog("checked for updates on {$updatedCount} mods");

        unset($this->mods, $this->outdatedMods);

        $outdatedCount = $this->outdatedMods->count();

        if ($outdatedCount > 0) {
            $this->dispatch('toast', message: "{$outdatedCount} mod(s) have updates available.", variant: 'info');
        } else {
            $this->dispatch('toast', message: 'All mods are up to date.', variant: 'success');
        }
    }

    public function updateAllOutdated(): void
    {
        $mods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Installed)
            ->get()
            ->filter(fn (WorkshopMod $mod) => $mod->isOutdated());

        if ($mods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::query()->latest()->first()?->mod_download_batch_size ?? 5;

        foreach ($mods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Queued, 'progress_pct' => 0]);
        }

        foreach ($mods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                BatchDownloadModsJob::dispatch($batch);
            }
        }

        $this->auditLog('queued update for '.$mods->count().' outdated mods in batches of '.$batchSize);

        $this->selectedMods = [];
        unset($this->mods, $this->installedModStats, $this->failedMods, $this->outdatedMods, $this->selectableMods, $this->isAllSelected);
    }

    public function toggleLogs(int $modId): void
    {
        $mod = $this->mods->firstWhere('id', $modId);
        $isActive = $mod && ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued);
        $this->showLogs[$modId] = ! ($this->showLogs[$modId] ?? $isActive);
    }

    public function addMod(): void
    {
        $this->validate([
            'workshopId' => ['required', 'numeric', 'min:1'],
        ]);

        $workshopId = (int) $this->workshopId;

        $mod = WorkshopMod::query()->firstOrCreate(
            ['workshop_id' => $workshopId],
            [
                'name' => null,
                'installation_status' => InstallationStatus::Queued,
            ]
        );

        if ($mod->installation_status === InstallationStatus::Failed) {
            $mod->update(['installation_status' => InstallationStatus::Queued]);
        }

        if ($mod->installation_status !== InstallationStatus::Installed) {
            DownloadModJob::dispatch($mod);
            $this->auditLog("queued mod download: workshop ID {$workshopId}");
        }

        $this->workshopId = '';
        unset($this->mods);
    }

    public function retryMod(WorkshopMod $mod): void
    {
        $mod->update(['installation_status' => InstallationStatus::Queued]);

        DownloadModJob::dispatch($mod);
        $this->auditLog("retried mod download: '{$mod->name}' ({$mod->workshop_id})");
        unset($this->mods);
    }

    public function retryAllFailed(): void
    {
        $failedMods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Failed)
            ->get();

        if ($failedMods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::query()->latest()->first()?->mod_download_batch_size ?? 5;

        foreach ($failedMods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Queued]);
        }

        foreach ($failedMods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                BatchDownloadModsJob::dispatch($batch);
            }
        }

        $this->auditLog('retried all failed mods ('.$failedMods->count().' mods in batches of '.$batchSize.')');
        unset($this->mods, $this->failedMods, $this->outdatedMods);
    }

    public function deleteMod(WorkshopMod $mod): void
    {
        if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued) {
            return;
        }

        $mod->presets()->detach();
        $mod->delete();

        $path = $mod->getInstallationPath();

        if (is_dir($path)) {
            \Illuminate\Support\Facades\Process::run(['rm', '-rf', $path]);
        }

        $this->auditLog("deleted mod '{$mod->name}' ({$mod->workshop_id})");

        unset($this->mods, $this->installedModStats, $this->outdatedMods);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Computed]
    public function reforgerMods()
    {
        return ReforgerMod::query()->orderBy('name')->get();
    }

    public function addReforgerMod(): void
    {
        $this->validate([
            'reforgerModId' => ['required', 'string', 'unique:reforger_mods,mod_id'],
            'reforgerModName' => ['required', 'string'],
        ]);

        ReforgerMod::create([
            'mod_id' => $this->reforgerModId,
            'name' => $this->reforgerModName,
        ]);

        $this->auditLog("added Reforger mod: '{$this->reforgerModName}' ({$this->reforgerModId})");

        $this->reforgerModId = '';
        $this->reforgerModName = '';

        unset($this->reforgerMods);

        $this->dispatch('toast', message: __('Reforger mod added.'), variant: 'success');
    }

    public function deleteReforgerMod(ReforgerMod $mod): void
    {
        $mod->presets()->detach();
        $mod->delete();

        $this->auditLog("deleted Reforger mod: '{$mod->name}' ({$mod->mod_id})");

        unset($this->reforgerMods);

        $this->dispatch('toast', message: __('Reforger mod deleted.'), variant: 'success');
    }
}; ?>

<section class="w-full" @if($activeTab === 'workshop' && $this->shouldPoll) wire:poll.5s @endif>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Mods') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Download and manage mods for your game servers.') }}</flux:text>
    </div>

    <div class="flex items-center gap-2 mb-6">
        <flux:button wire:click="switchTab('workshop')" :variant="$activeTab === 'workshop' ? 'primary' : 'ghost'">
            {{ __('Workshop Mods') }}
        </flux:button>
        <flux:button wire:click="switchTab('reforger')" :variant="$activeTab === 'reforger' ? 'primary' : 'ghost'">
            {{ __('Reforger Mods') }}
        </flux:button>
    </div>

    @if ($activeTab === 'workshop')
        <div class="mb-4">
            <flux:text>
                {{ __('Download and manage Steam Workshop mods.') }}
                @if ($this->installedModStats['count'] > 0)
                    @php
                        $totalBytes = $this->installedModStats['total_size'];
                        $formattedSize = $totalBytes >= 1073741824
                            ? number_format($totalBytes / 1073741824, 2) . ' GB'
                            : number_format($totalBytes / 1048576, 1) . ' MB';
                    @endphp
                    <span class="ml-1 text-zinc-500 dark:text-zinc-400">&mdash; {{ $this->installedModStats['count'] }} {{ __('installed') }}, {{ $formattedSize }} {{ __('total') }}</span>
                @endif
            </flux:text>
        </div>

        <div class="flex items-end justify-between gap-4 mb-6">
            <form wire:submit="addMod" class="flex items-end gap-4 max-w-xl">
                <div class="flex-1">
                    <flux:input wire:model="workshopId" :label="__('Workshop ID')" :placeholder="__('e.g. 463939057')" />
                </div>
                <div>
                    <flux:button variant="primary" type="submit" icon="plus">
                        {{ __('Add Mod') }}
                    </flux:button>
                </div>
            </form>

            <div class="flex items-center gap-2">
                <flux:button wire:click="checkForUpdates" icon="magnifying-glass">
                    {{ __('Check for Updates') }}
                </flux:button>

                @if ($this->outdatedMods->isNotEmpty())
                    <flux:button wire:click="updateAllOutdated" wire:confirm="{{ __('Update all :count outdated mods? This will re-download them from Steam Workshop.', ['count' => $this->outdatedMods->count()]) }}" icon="arrow-path" variant="primary">
                        {{ __('Update All Outdated') }} ({{ $this->outdatedMods->count() }})
                    </flux:button>
                @endif

                @if (count($this->selectedMods) > 0)
                    <flux:button wire:click="updateSelected" wire:confirm="{{ __('Update :count selected mods? This will re-download them from Steam Workshop.', ['count' => count($this->selectedMods)]) }}" icon="arrow-path">
                        {{ __('Update Selected') }} ({{ count($this->selectedMods) }})
                    </flux:button>
                @endif

                @if ($this->failedMods->isNotEmpty())
                    <flux:button wire:click="retryAllFailed" wire:confirm="{{ __('Retry all :count failed mods?', ['count' => $this->failedMods->count()]) }}" icon="arrow-path" variant="outline">
                        {{ __('Retry All Failed') }} ({{ $this->failedMods->count() }})
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($this->mods->isEmpty() && $this->search === '')
            <flux:callout>
                {{ __('No mods added yet. Enter a Steam Workshop ID above to download a mod.') }}
            </flux:callout>
        @else
            <div class="mb-4 max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by name or workshop ID...') }}" icon="magnifying-glass" clearable />
            </div>

            @if ($this->mods->isEmpty())
                <flux:callout>
                    {{ __('No mods match your search.') }}
                </flux:callout>
            @else
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-600 dark:text-zinc-300">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <flux:checkbox wire:click="toggleSelectAll" :checked="$this->isAllSelected" />
                            </th>
                            <th class="px-4 py-3 font-medium">{{ __('Workshop ID') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium">
                                <button wire:click="toggleSort('file_size')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">
                                    {{ __('Size') }}
                                    @if ($sortBy === 'file_size')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5" />
                                    @else
                                        <flux:icon name="chevron-up-down" class="size-3.5 opacity-40" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <button wire:click="toggleSort('status')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">
                                    {{ __('Status') }}
                                    @if ($sortBy === 'status')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5" />
                                    @else
                                        <flux:icon name="chevron-up-down" class="size-3.5 opacity-40" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <button wire:click="toggleSort('steam_updated_at')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">
                                    {{ __('Workshop Updated') }}
                                    @if ($sortBy === 'steam_updated_at')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5" />
                                    @else
                                        <flux:icon name="chevron-up-down" class="size-3.5 opacity-40" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <button wire:click="toggleSort('installed_at')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">
                                    {{ __('Installed') }}
                                    @if ($sortBy === 'installed_at')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5" />
                                    @else
                                        <flux:icon name="chevron-up-down" class="size-3.5 opacity-40" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    @foreach ($this->mods as $mod)
                        <x-log-viewer
                            channel="mod-download.{{ $mod->id }}"
                            event="ModDownloadOutput"
                            :track-progress="true"
                            max-height="max-h-48"
                            tag="tbody"
                            class="divide-y divide-zinc-200 dark:divide-zinc-700"
                            wire:key="mod-group-{{ $mod->id }}"
                        >
                            <tr wire:key="mod-{{ $mod->id }}">
                                <td class="px-4 py-3">
                                    <flux:checkbox wire:model.live="selectedMods" :value="$mod->id" :disabled="$mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued" />
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $mod->workshop_id }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $mod->name ?? __('Fetching...') }}</span>
                                        @if ($mod->game_type)
                                            <flux:badge size="sm">{{ $mod->game_type->label() }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($mod->file_size)
                                        {{ number_format($mod->file_size / 1048576, 1) }} MB
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                @if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued)
                                    <div class="w-32">
                                        <div class="flex items-center gap-2 mb-1">
                                            <flux:badge :variant="$mod->installation_status === InstallationStatus::Queued ? 'secondary' : 'warning'" size="sm">
                                                {{ $mod->installation_status === InstallationStatus::Queued ? __('Queued') : __('Downloading') }}
                                            </flux:badge>
                                            <span class="text-xs font-medium" x-text="progress + '%'"></span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-1.5 rounded-full bg-amber-500 transition-all duration-500" x-bind:style="'width: ' + progress + '%'"></div>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge :variant="$mod->installation_status->badgeVariant()" size="sm">
                                            {{ ucfirst($mod->installation_status->value) }}
                                        </flux:badge>
                                        @if ($mod->isOutdated())
                                            <flux:badge variant="warning" size="sm">
                                                {{ __('Update available') }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $mod->steam_updated_at?->format('M j, Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $mod->installed_at?->format('M j, Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued)
                                            <flux:button size="sm" variant="ghost" wire:click="toggleLogs({{ $mod->id }})" icon="command-line">
                                                {{ __('Logs') }}
                                            </flux:button>
                                        @endif
                                        @if ($mod->installation_status === InstallationStatus::Failed)
                                            <flux:button size="sm" wire:click="retryMod({{ $mod->id }})" icon="arrow-path">
                                                {{ __('Retry') }}
                                            </flux:button>
                                        @endif
                                        <flux:button size="sm" variant="danger" wire:click="deleteMod({{ $mod->id }})" wire:confirm="Delete this mod?" icon="trash" :disabled="$mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>

                            <x-slot:log>
                                @if ($this->showLogs[$mod->id] ?? ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued))
                                    <tr wire:key="mod-logs-{{ $mod->id }}">
                                        <td colspan="8" class="px-4 py-3">
                                            <div class="rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs max-h-48 overflow-y-auto" x-ref="logContainer">
                                                <template x-if="lines.length === 0">
                                                    <div class="text-zinc-500">{{ __('Waiting for output...') }}</div>
                                                </template>
                                                <template x-for="(line, index) in lines" :key="index">
                                                    <div class="whitespace-pre-wrap break-all" x-text="line"></div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </x-slot:log>
                        </x-log-viewer>
                    @endforeach
                </table>
            </div>
            @endif
        @endif
    @elseif ($activeTab === 'reforger')
        <div class="mb-4">
            <flux:text>{{ __('Manage Arma Reforger mods. These are stored as database entries for use in server configurations.') }}</flux:text>
        </div>

        <form wire:submit="addReforgerMod" class="flex items-end gap-4 max-w-2xl mb-6">
            <div class="flex-1">
                <flux:input wire:model="reforgerModId" :label="__('Mod ID')" :placeholder="__('e.g. 591AF5BDA9F7CE8B')" />
            </div>
            <div class="flex-1">
                <flux:input wire:model="reforgerModName" :label="__('Name')" :placeholder="__('e.g. My Reforger Mod')" />
            </div>
            <div>
                <flux:button variant="primary" type="submit" icon="plus">
                    {{ __('Add Mod') }}
                </flux:button>
            </div>
        </form>

        @if ($this->reforgerMods->isEmpty())
            <flux:callout>
                {{ __('No Reforger mods added yet. Enter a mod ID and name above to add one.') }}
            </flux:callout>
        @else
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-600 dark:text-zinc-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Mod ID') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->reforgerMods as $mod)
                            <tr wire:key="reforger-mod-{{ $mod->id }}">
                                <td class="px-4 py-3 font-mono text-xs">{{ $mod->mod_id }}</td>
                                <td class="px-4 py-3">{{ $mod->name }}</td>
                                <td class="px-4 py-3">
                                    <flux:button size="sm" variant="danger" wire:click="deleteReforgerMod({{ $mod->id }})" wire:confirm="{{ __('Delete this Reforger mod?') }}" icon="trash">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</section>
