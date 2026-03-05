<?php

use App\Models\ModPreset;
use App\Models\Server;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Server')] class extends Component
{
    public Server $server;

    public string $name = '';

    public int $port = 2302;

    public int $query_port = 2303;

    public int $max_players = 32;

    public string $password = '';

    public string $admin_password = '';

    public string $description = '';

    public ?int $active_preset_id = null;

    public int $headless_client_count = 0;

    public string $additional_params = '';

    #[Computed]
    public function presets()
    {
        return ModPreset::query()->orderBy('name')->get();
    }

    public function mount(Server $server): void
    {
        $this->server = $server;
        $this->name = $server->name;
        $this->port = $server->port;
        $this->query_port = $server->query_port;
        $this->max_players = $server->max_players;
        $this->password = $server->password ?? '';
        $this->admin_password = $server->admin_password ?? '';
        $this->description = $server->description ?? '';
        $this->active_preset_id = $server->active_preset_id;
        $this->headless_client_count = $server->headless_client_count;
        $this->additional_params = $server->additional_params ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'max_players' => ['required', 'integer', 'min:1', 'max:256'],
            'password' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active_preset_id' => ['nullable', 'exists:mod_presets,id'],
            'headless_client_count' => ['required', 'integer', 'min:0', 'max:10'],
            'additional_params' => ['nullable', 'string'],
        ]);

        $this->server->update($validated);

        session()->flash('status', "Server '{$validated['name']}' updated successfully.");

        $this->redirect(route('servers.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Server') }}: {{ $server->name }}</flux:heading>
        <flux:text class="mt-2">{{ __('Update server configuration.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-2xl">
        <flux:input wire:model="name" :label="__('Server Name')" required />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="port" :label="__('Game Port')" type="number" required />
            <flux:input wire:model="query_port" :label="__('Query Port')" type="number" required />
        </div>

        <flux:input wire:model="max_players" :label="__('Max Players')" type="number" required />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="password" :label="__('Server Password')" type="text" :placeholder="__('Leave empty for no password')" />
            <flux:input wire:model="admin_password" :label="__('Admin Password')" type="text" />
        </div>

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <flux:select wire:model="active_preset_id" :label="__('Active Mod Preset')">
            <flux:select.option :value="null">{{ __('None') }}</flux:select.option>
            @foreach ($this->presets as $preset)
                <flux:select.option :value="$preset->id">{{ $preset->name }} ({{ $preset->mods()->count() }} mods)</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="headless_client_count" :label="__('Headless Clients')" type="number" min="0" max="10" />

        <flux:textarea wire:model="additional_params" :label="__('Additional Launch Parameters')" rows="2" :placeholder="__('-loadMissionToMemory -enableHT')" />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Update Server') }}</flux:button>
            <flux:button :href="route('servers.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
