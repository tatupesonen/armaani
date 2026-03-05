<?php

use App\Jobs\InstallServerJob;
use App\Models\Server;
use App\Services\ServerProcessService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Servers')] class extends Component
{
    public bool $confirmingDelete = false;

    public ?int $deletingServerId = null;

    #[Computed]
    public function servers()
    {
        return Server::query()->with('activePreset')->get();
    }

    public function getStatus(Server $server): string
    {
        return app(ServerProcessService::class)->getStatus($server)->value;
    }

    public function startServer(Server $server): void
    {
        app(ServerProcessService::class)->start($server);
    }

    public function stopServer(Server $server): void
    {
        app(ServerProcessService::class)->stop($server);
    }

    public function restartServer(Server $server): void
    {
        app(ServerProcessService::class)->restart($server);
    }

    public function installServer(Server $server): void
    {
        InstallServerJob::dispatch($server);

        session()->flash('status', "Server installation queued for '{$server->name}'.");
    }

    public function confirmDelete(int $serverId): void
    {
        $this->confirmingDelete = true;
        $this->deletingServerId = $serverId;
    }

    public function deleteServer(): void
    {
        if ($this->deletingServerId) {
            Server::query()->find($this->deletingServerId)?->delete();
        }

        $this->confirmingDelete = false;
        $this->deletingServerId = null;

        unset($this->servers);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Servers') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Manage your Arma 3 server instances.') }}</flux:text>
        </div>
        <flux:button variant="primary" :href="route('servers.create')" wire:navigate icon="plus">
            {{ __('New Server') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-4">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if ($this->servers->isEmpty())
        <flux:callout>
            {{ __('No servers configured yet. Create your first server to get started.') }}
        </flux:callout>
    @else
        <div class="space-y-4" wire:poll.5s>
            @foreach ($this->servers as $server)
                @php $status = $this->getStatus($server); @endphp
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4" wire:key="server-{{ $server->id }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading size="lg">{{ $server->name }}</flux:heading>
                                    <flux:badge :variant="$status === 'running' ? 'success' : 'secondary'" size="sm">
                                        {{ ucfirst($status) }}
                                    </flux:badge>
                                </div>
                                <flux:text class="mt-1">
                                    {{ __('Port') }}: {{ $server->port }} &middot;
                                    {{ __('Players') }}: {{ $server->max_players }}
                                    @if ($server->activePreset)
                                        &middot; {{ __('Preset') }}: {{ $server->activePreset->name }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($status === 'running')
                                <flux:button size="sm" variant="danger" wire:click="stopServer({{ $server->id }})" wire:confirm="Are you sure you want to stop this server?" icon="stop">
                                    {{ __('Stop') }}
                                </flux:button>
                                <flux:button size="sm" wire:click="restartServer({{ $server->id }})" wire:confirm="Are you sure you want to restart this server?" icon="arrow-path">
                                    {{ __('Restart') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="startServer({{ $server->id }})" icon="play">
                                    {{ __('Start') }}
                                </flux:button>
                                <flux:button size="sm" wire:click="installServer({{ $server->id }})" icon="arrow-down-tray">
                                    {{ __('Install/Update') }}
                                </flux:button>
                            @endif
                            <flux:button size="sm" :href="route('servers.edit', $server)" wire:navigate icon="pencil">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $server->id }})" icon="trash">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model="confirmingDelete">
        <flux:heading>{{ __('Delete Server') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete this server? This action cannot be undone.') }}</flux:text>
        <div class="flex justify-end gap-2 mt-4">
            <flux:button wire:click="$set('confirmingDelete', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteServer">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>
</section>
