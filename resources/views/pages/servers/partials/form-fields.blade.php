{{-- Shared server form fields. $prefix should be 'create' or 'edit'. --}}
@php
    $gameType = $prefix === 'create' ? $this->createGameType : ($this->editingGameType ?? 'arma3');
    $filteredGameInstalls = $this->gameInstalls->where('game_type.value', $gameType);
    $filteredPresets = $this->presets->where('game_type.value', $gameType);
@endphp

{{-- Basic Settings (expanded by default) --}}
<div x-data="{ open: true }" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
    <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-3 px-4 py-3 text-left">
        <div class="flex-1">
            <span class="text-base font-semibold text-zinc-800 dark:text-white">{{ __('Basic Settings') }}</span>
            <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Server name, ports, player limits, passwords, and game configuration.') }}</span>
        </div>
        <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform duration-200" ::class="open && 'rotate-180'" />
    </button>
    <div x-show="open" x-transition.opacity.duration.200ms class="space-y-4 border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
        <flux:input wire:model="{{ $prefix }}Name" :label="__('Server Name')" required />

        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('Game Port') }}</flux:label>
                <flux:input wire:model.change="{{ $prefix }}Port" type="number" required />
                <flux:error name="{{ $prefix }}Port" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Query Port') }}</flux:label>
                <flux:input wire:model="{{ $prefix }}QueryPort" type="number" required />
                <flux:error name="{{ $prefix }}QueryPort" />
            </flux:field>
        </div>

        <flux:input wire:model="{{ $prefix }}MaxPlayers" :label="__('Max Players')" type="number" required />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="{{ $prefix }}Password" :label="__('Server Password')" type="text" :placeholder="__('Leave empty for no password')" />
            <flux:input wire:model="{{ $prefix }}AdminPassword" :label="__('Admin Password')" type="text" />
        </div>

        <flux:textarea wire:model="{{ $prefix }}Description" :label="__('Description')" rows="2" />

        <flux:field>
            <flux:label>{{ __('Game Install') }}</flux:label>
            <flux:select wire:model="{{ $prefix }}GameInstallId">
                @foreach ($filteredGameInstalls as $install)
                    <flux:select.option :value="$install->id">
                        {{ $install->name }} ({{ $install->branch }})
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="{{ $prefix }}GameInstallId" />
            @if ($filteredGameInstalls->isEmpty())
                <flux:description>{{ __('No game installs available. Add one on the Game Installs page first.') }}</flux:description>
            @endif
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Active Mod Preset') }}</flux:label>
            <flux:select wire:model="{{ $prefix }}ActivePresetId">
                <flux:select.option :value="null">{{ __('None') }}</flux:select.option>
                @foreach ($filteredPresets as $preset)
                    <flux:select.option :value="$preset->id">{{ $preset->name }} ({{ $preset->mods()->count() }} mods)</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="{{ $prefix }}ActivePresetId" />
        </flux:field>
    </div>
</div>
