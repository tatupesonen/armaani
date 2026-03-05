<?php

use App\Models\SteamAccount;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Steam Settings')] class extends Component
{
    public string $username = '';

    public string $password = '';

    public string $auth_token = '';

    public function mount(): void
    {
        $account = SteamAccount::query()->latest()->first();

        if ($account) {
            $this->username = $account->username;
            $this->auth_token = $account->getRawOriginal('auth_token') ? '********' : '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
        ]);

        $account = SteamAccount::query()->latest()->first();

        $data = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        if ($this->auth_token !== '********') {
            $data['auth_token'] = $this->auth_token ?: null;
        }

        if ($account) {
            $account->update($data);
        } else {
            $account = SteamAccount::query()->create($data);
        }

        $this->password = '';
        $this->dispatch('steam-settings-saved');
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Steam Settings') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Configure Steam credentials used by SteamCMD to download server files and workshop mods. Credentials are stored encrypted.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-xl">
        <flux:input wire:model="username" :label="__('Steam Username')" required autocomplete="off" />

        <flux:input wire:model="password" :label="__('Steam Password')" type="password" required autocomplete="new-password" />

        <flux:input wire:model="auth_token" :label="__('Steam Guard Token')" :placeholder="__('Email token from Steam Guard (if 2FA is enabled)')" autocomplete="off" />

        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Steam Guard: If you have email-based Steam Guard enabled, you may need to attempt a server install first (which will fail), then enter the token sent to your email.') }}
        </flux:callout>

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">
                {{ __('Save Credentials') }}
            </flux:button>
            <x-action-message on="steam-settings-saved">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
