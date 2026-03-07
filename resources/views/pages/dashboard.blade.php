<?php

use App\Enums\InstallationStatus;
use App\Enums\ServerStatus;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function serverStats(): array
    {
        $servers = Server::query()->get(['id', 'status', 'max_players']);
        $statusCounts = $servers->groupBy(fn (Server $s) => $s->status->value)->map->count();

        return [
            'total' => $servers->count(),
            'running' => ($statusCounts[ServerStatus::Running->value] ?? 0) + ($statusCounts[ServerStatus::Booting->value] ?? 0),
            'stopped' => $statusCounts[ServerStatus::Stopped->value] ?? 0,
        ];
    }

    #[Computed]
    public function gameInstallStats(): array
    {
        $installs = GameInstall::query()->get(['id', 'installation_status', 'disk_size_bytes']);

        return [
            'total' => $installs->count(),
            'installed' => $installs->where('installation_status', InstallationStatus::Installed)->count(),
            'disk_size' => $installs->sum('disk_size_bytes'),
        ];
    }

    #[Computed]
    public function modStats(): array
    {
        $result = WorkshopMod::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN installation_status = ? THEN 1 ELSE 0 END) as installed', [InstallationStatus::Installed->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN installation_status = ? THEN file_size ELSE 0 END), 0) as total_size', [InstallationStatus::Installed->value])
            ->first();

        return [
            'total' => (int) $result->total,
            'installed' => (int) $result->installed,
            'total_size' => (int) $result->total_size,
        ];
    }

    #[Computed]
    public function presetCount(): int
    {
        return ModPreset::query()->count();
    }

    #[Computed]
    public function missionCount(): int
    {
        $path = config('arma.missions_base_path');

        if (! is_dir($path)) {
            return 0;
        }

        return count(glob($path.'/*.pbo') ?: []);
    }

    #[Computed]
    public function queueStats(): array
    {
        return [
            'pending' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
        ];
    }

    #[Computed]
    public function steamConfigured(): bool
    {
        return SteamAccount::query()->exists();
    }

    #[Computed]
    public function servers()
    {
        return Server::query()->with('gameInstall')->orderBy('name')->get();
    }

    #[Computed]
    public function diskUsage(): array
    {
        $path = storage_path();
        $total = (float) disk_total_space($path);
        $free = (float) disk_free_space($path);
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    #[Computed]
    public function memoryUsage(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0, 'available' => false];
        }

        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availMatch);

        $totalKb = (int) ($totalMatch[1] ?? 0);
        $availKb = (int) ($availMatch[1] ?? 0);
        $usedKb = $totalKb - $availKb;

        return [
            'total' => $totalKb * 1024,
            'used' => $usedKb * 1024,
            'free' => $availKb * 1024,
            'percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0,
            'available' => true,
        ];
    }

    #[Computed]
    public function cpuInfo(): array
    {
        $loadAvg = sys_getloadavg();
        $cores = 1;

        if (is_readable('/proc/cpuinfo')) {
            $cores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor') ?: 1;
        }

        $loadPercent = min(round(($loadAvg[0] / $cores) * 100, 1), 100);

        return [
            'load_1' => round($loadAvg[0], 2),
            'load_5' => round($loadAvg[1], 2),
            'load_15' => round($loadAvg[2], 2),
            'cores' => $cores,
            'percent' => $loadPercent,
        ];
    }

    #[On('echo:servers,ServerStatusChanged')]
    public function onServerStatusChanged(): void
    {
        unset($this->serverStats, $this->servers);
    }

    public function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2).' TB';
        }

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1024, 0).' KB';
    }

    public function usageBarColor(float $percent): string
    {
        if ($percent >= 90) {
            return 'bg-red-500';
        }

        if ($percent >= 75) {
            return 'bg-amber-500';
        }

        return 'bg-emerald-500';
    }
}; ?>

<section class="w-full" wire:poll.30s>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Overview of your Arma 3 server manager.') }}</flux:text>
    </div>

    {{-- Stat Cards --}}
    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        {{-- Servers --}}
        <a href="{{ route('servers.index') }}" class="rounded-lg border border-zinc-200 p-4 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/50">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/10">
                    <flux:icon.server class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Servers') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->serverStats['total'] }}</div>
                </div>
            </div>
            @if ($this->serverStats['total'] > 0)
                <flux:text class="mt-3 text-xs">
                    <span class="text-emerald-600 dark:text-emerald-400">{{ $this->serverStats['running'] }} {{ __('running') }}</span>
                    &middot;
                    {{ $this->serverStats['stopped'] }} {{ __('stopped') }}
                </flux:text>
            @endif
        </a>

        {{-- Game Installs --}}
        <a href="{{ route('game-installs.index') }}" class="rounded-lg border border-zinc-200 p-4 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/50">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-500/10">
                    <flux:icon.arrow-down-tray class="size-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Game Installs') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->gameInstallStats['total'] }}</div>
                </div>
            </div>
            @if ($this->gameInstallStats['disk_size'] > 0)
                <flux:text class="mt-3 text-xs">
                    {{ $this->formatBytes($this->gameInstallStats['disk_size']) }} {{ __('on disk') }}
                </flux:text>
            @endif
        </a>

        {{-- Workshop Mods --}}
        <a href="{{ route('mods.index') }}" class="rounded-lg border border-zinc-200 p-4 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/50">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-500/10">
                    <flux:icon.puzzle-piece class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Workshop Mods') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->modStats['installed'] }}</div>
                </div>
            </div>
            @if ($this->modStats['total_size'] > 0)
                <flux:text class="mt-3 text-xs">
                    {{ $this->formatBytes($this->modStats['total_size']) }} {{ __('total') }}
                    @if ($this->modStats['total'] !== $this->modStats['installed'])
                        &middot; {{ $this->modStats['total'] }} {{ __('tracked') }}
                    @endif
                </flux:text>
            @endif
        </a>

        {{-- Missions --}}
        <a href="{{ route('missions.index') }}" class="rounded-lg border border-zinc-200 p-4 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/50">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                    <flux:icon.document-arrow-up class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Missions') }}</flux:text>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->missionCount }}</div>
                </div>
            </div>
            <flux:text class="mt-3 text-xs">{{ __('PBO files') }}</flux:text>
        </a>
    </div>

    {{-- System Resources --}}
    <flux:heading size="lg" class="mb-4">{{ __('System Resources') }}</flux:heading>
    <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
        {{-- Disk Usage --}}
        @php $disk = $this->diskUsage; @endphp
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3 flex items-center justify-between">
                <flux:text class="text-sm font-medium">{{ __('Disk Usage') }}</flux:text>
                <span class="text-sm font-semibold {{ $disk['percent'] >= 90 ? 'text-red-600 dark:text-red-400' : ($disk['percent'] >= 75 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white') }}">
                    {{ $disk['percent'] }}%
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-2 rounded-full transition-all duration-500 {{ $this->usageBarColor($disk['percent']) }}" style="width: {{ min($disk['percent'], 100) }}%"></div>
            </div>
            <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ $this->formatBytes($disk['used']) }} {{ __('used') }}</span>
                <span>{{ $this->formatBytes($disk['free']) }} {{ __('free') }}</span>
            </div>
            <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                {{ $this->formatBytes($disk['total']) }} {{ __('total') }}
            </flux:text>
        </div>

        {{-- Memory --}}
        @php $mem = $this->memoryUsage; @endphp
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3 flex items-center justify-between">
                <flux:text class="text-sm font-medium">{{ __('Memory') }}</flux:text>
                @if ($mem['available'])
                    <span class="text-sm font-semibold {{ $mem['percent'] >= 90 ? 'text-red-600 dark:text-red-400' : ($mem['percent'] >= 75 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white') }}">
                        {{ $mem['percent'] }}%
                    </span>
                @endif
            </div>
            @if ($mem['available'])
                <div class="h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-2 rounded-full transition-all duration-500 {{ $this->usageBarColor($mem['percent']) }}" style="width: {{ min($mem['percent'], 100) }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                    <span>{{ $this->formatBytes($mem['used']) }} {{ __('used') }}</span>
                    <span>{{ $this->formatBytes($mem['free']) }} {{ __('free') }}</span>
                </div>
                <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                    {{ $this->formatBytes($mem['total']) }} {{ __('total') }}
                </flux:text>
            @else
                <flux:text class="text-sm text-zinc-400">{{ __('Not available') }}</flux:text>
            @endif
        </div>

        {{-- CPU Load --}}
        @php $cpu = $this->cpuInfo; @endphp
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3 flex items-center justify-between">
                <flux:text class="text-sm font-medium">{{ __('CPU Load') }}</flux:text>
                <span class="text-sm font-semibold {{ $cpu['percent'] >= 90 ? 'text-red-600 dark:text-red-400' : ($cpu['percent'] >= 75 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white') }}">
                    {{ $cpu['percent'] }}%
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-2 rounded-full transition-all duration-500 {{ $this->usageBarColor($cpu['percent']) }}" style="width: {{ min($cpu['percent'], 100) }}%"></div>
            </div>
            <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ $cpu['load_1'] }} &middot; {{ $cpu['load_5'] }} &middot; {{ $cpu['load_15'] }}</span>
                <span>{{ $cpu['cores'] }} {{ __('cores') }}</span>
            </div>
            <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                {{ __('Load avg: 1m / 5m / 15m') }}
            </flux:text>
        </div>
    </div>

    {{-- Server Overview + Quick Info --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Server Status --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Server Status') }}</flux:heading>
                <a href="{{ route('servers.index') }}" class="text-xs text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                    {{ __('View all') }} &rarr;
                </a>
            </div>
            @forelse ($this->servers as $server)
                <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-zinc-800" wire:key="dash-server-{{ $server->id }}">
                    <div class="flex items-center gap-3">
                        <flux:badge :variant="match($server->status) { ServerStatus::Running => 'success', ServerStatus::Starting, ServerStatus::Stopping, ServerStatus::Booting => 'warning', default => 'secondary' }" size="sm">
                            {{ ucfirst($server->status->value) }}
                        </flux:badge>
                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $server->name }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($server->gameInstall)
                            <flux:text class="text-xs">{{ $server->gameInstall->name }}</flux:text>
                        @endif
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">:{{ $server->port }}</span>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-center">
                    <flux:text class="text-sm text-zinc-500">{{ __('No servers configured.') }}</flux:text>
                    <a href="{{ route('servers.index') }}" class="mt-1 inline-block text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400">
                        {{ __('Create your first server') }} &rarr;
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Quick Info --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Quick Info') }}</flux:heading>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                <a href="{{ route('presets.index') }}" class="flex items-center justify-between px-4 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <flux:text class="text-sm">{{ __('Mod Presets') }}</flux:text>
                    <flux:badge variant="secondary" size="sm">{{ $this->presetCount }}</flux:badge>
                </a>
                <div class="flex items-center justify-between px-4 py-3">
                    <flux:text class="text-sm">{{ __('Queue Jobs') }}</flux:text>
                    <flux:badge :variant="$this->queueStats['pending'] > 0 ? 'warning' : 'secondary'" size="sm">
                        {{ $this->queueStats['pending'] }}
                    </flux:badge>
                </div>
                <div class="flex items-center justify-between px-4 py-3">
                    <flux:text class="text-sm">{{ __('Failed Jobs') }}</flux:text>
                    <flux:badge :variant="$this->queueStats['failed'] > 0 ? 'danger' : 'secondary'" size="sm">
                        {{ $this->queueStats['failed'] }}
                    </flux:badge>
                </div>
                @if ($this->steamConfigured)
                    <div class="flex items-center justify-between px-4 py-3">
                        <flux:text class="text-sm">{{ __('Steam Account') }}</flux:text>
                        <flux:badge variant="success" size="sm">{{ __('Configured') }}</flux:badge>
                    </div>
                @else
                    <a href="{{ route('steam-settings') }}" class="flex items-center justify-between px-4 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <flux:text class="text-sm">{{ __('Steam Account') }}</flux:text>
                        <flux:badge variant="danger" size="sm">{{ __('Not configured') }}</flux:badge>
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
