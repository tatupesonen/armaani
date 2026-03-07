{{-- Arma 3-specific server form fields. $prefix should be 'create' or 'edit'. --}}

{{-- Server Rules (collapsed by default) --}}
<div x-data="{ open: false }" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
    <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-3 px-4 py-3 text-left">
        <div class="flex-1">
            <span class="text-base font-semibold text-zinc-800 dark:text-white">{{ __('Server Rules') }}</span>
            <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Security, anti-cheat, voice communication, and server persistence options.') }}</span>
        </div>
        <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform duration-200" ::class="open && 'rotate-180'" />
    </button>
    <div x-show="open" x-transition.opacity.duration.200ms class="space-y-3 border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
        <flux:switch wire:model="{{ $prefix }}VerifySignatures" label="{{ __('Verify Signatures') }}" description="{{ __('Kick players with unsigned or modified addon files (verifySignatures=2). Disable for lenient modded servers.') }}" />
        <flux:separator variant="subtle" />
        <flux:switch wire:model="{{ $prefix }}AllowedFilePatching" label="{{ __('Allow File Patching') }}" description="{{ __('Allow clients to use file patching (allowedFilePatching=2). Required by some mods like ACE.') }}" />
        <flux:separator variant="subtle" />
        <flux:switch wire:model="{{ $prefix }}BattleEye" label="{{ __('BattlEye Anti-Cheat') }}" description="{{ __('Enable BattlEye anti-cheat protection. May conflict with some mod setups.') }}" />
        <flux:separator variant="subtle" />
        <flux:switch wire:model="{{ $prefix }}VonEnabled" label="{{ __('Voice Over Network') }}" description="{{ __('Enable in-game voice communication.') }}" />
        <flux:separator variant="subtle" />
        <flux:switch wire:model="{{ $prefix }}Persistent" label="{{ __('Persistent Server') }}" description="{{ __('Keep the server running even when no players are connected.') }}" />
    </div>
</div>
