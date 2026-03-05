<?php

use App\Enums\InstallationStatus;
use App\Jobs\DownloadModJob;
use App\Models\WorkshopMod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Workshop Mods')] class extends Component
{
    public string $workshopId = '';

    /** @var array<int, bool> */
    public array $showLogs = [];

    #[Computed]
    public function mods()
    {
        return WorkshopMod::query()->orderByDesc('created_at')->get();
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->mods->contains(fn (WorkshopMod $mod) => $mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued
        );
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
            Log::info('User '.auth()->id().' ('.auth()->user()->name.") queued mod download: workshop ID {$workshopId}");
        }

        $this->workshopId = '';
        unset($this->mods);
    }

    public function retryMod(WorkshopMod $mod): void
    {
        $mod->update(['installation_status' => InstallationStatus::Queued]);

        DownloadModJob::dispatch($mod);
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") retried mod download: '{$mod->name}' ({$mod->workshop_id})");
        unset($this->mods);
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

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted mod '{$mod->name}' ({$mod->workshop_id})");

        unset($this->mods);
    }

    public function statusVariant(InstallationStatus $status): string
    {
        return match ($status) {
            InstallationStatus::Installed => 'success',
            InstallationStatus::Installing => 'warning',
            InstallationStatus::Queued => 'secondary',
            InstallationStatus::Failed => 'danger',
        };
    }
}; ?>

<section class="w-full" @if($this->shouldPoll) wire:poll.5s @endif>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Workshop Mods') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Download and manage Steam Workshop mods.') }}</flux:text>
    </div>

    <form wire:submit="addMod" class="flex items-end gap-4 mb-6 max-w-xl">
        <div class="flex-1">
            <flux:input wire:model="workshopId" :label="__('Workshop ID')" :placeholder="__('e.g. 463939057')" />
        </div>
        <div>
            <flux:button variant="primary" type="submit" icon="plus">
                {{ __('Add Mod') }}
            </flux:button>
        </div>
    </form>

    @if ($this->mods->isEmpty())
        <flux:callout>
            {{ __('No mods added yet. Enter a Steam Workshop ID above to download a mod.') }}
        </flux:callout>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-600 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Workshop ID') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Size') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                @foreach ($this->mods as $mod)
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700" wire:key="mod-group-{{ $mod->id }}"
                        x-data="{
                            progress: {{ $mod->progress_pct }},
                            lines: [],
                            channel: null,
                            maxLines: 200,
                            init() {
                                @if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued)
                                    this.channel = window.Echo.channel('mod-download.{{ $mod->id }}');
                                    this.channel.listen('ModDownloadOutput', (event) => {
                                        this.progress = event.progressPct;
                                        this.lines.push(event.line);
                                        if (this.lines.length > this.maxLines) {
                                            this.lines = this.lines.slice(-this.maxLines);
                                        }
                                        this.$nextTick(() => this.scrollToBottom());
                                    });
                                @endif
                            },
                            scrollToBottom() {
                                if (this.$refs.logContainer) {
                                    this.$refs.logContainer.scrollTop = this.$refs.logContainer.scrollHeight;
                                }
                            },
                            destroy() {
                                if (this.channel) {
                                    window.Echo.leave('mod-download.{{ $mod->id }}');
                                    this.channel = null;
                                }
                            }
                        }"
                    >
                        <tr wire:key="mod-{{ $mod->id }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $mod->workshop_id }}</td>
                            <td class="px-4 py-3">{{ $mod->name ?? __('Fetching...') }}</td>
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
                                    <flux:badge :variant="$this->statusVariant($mod->installation_status)" size="sm">
                                        {{ ucfirst($mod->installation_status->value) }}
                                    </flux:badge>
                                @endif
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
                        @if ($this->showLogs[$mod->id] ?? ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued))
                            <tr wire:key="mod-logs-{{ $mod->id }}">
                                <td colspan="5" class="px-4 py-3">
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
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</section>
