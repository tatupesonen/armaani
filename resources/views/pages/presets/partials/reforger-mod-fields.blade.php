@if ($availableReforgerMods->isNotEmpty())
    <flux:field>
        <flux:label>{{ __('Select Mods') }}</flux:label>
        <div class="mt-2 max-h-80 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($availableReforgerMods as $mod)
                <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="reforger-mod-{{ $mod->id }}">
                    <flux:checkbox wire:model="selectedReforgerModIds" :value="$mod->id" />
                    <div>
                        <div class="text-sm font-medium">{{ $mod->name ?? __('Mod') . ' ' . $mod->mod_id }}</div>
                        <div class="text-xs text-zinc-500">{{ __('ID') }}: {{ $mod->mod_id }}</div>
                    </div>
                </label>
            @endforeach
        </div>
    </flux:field>
@else
    <flux:callout>
        {{ __('No Reforger mods available. Add mods from the Reforger Mods page first.') }}
    </flux:callout>
@endif
