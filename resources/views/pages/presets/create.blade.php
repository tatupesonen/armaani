<?php

use App\Enums\GameType;
use App\Livewire\Concerns\AuditsActions;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use App\Models\WorkshopMod;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Preset')] class extends Component
{
    use AuditsActions;

    public string $createGameType = 'arma3';

    public string $name = '';

    /** @var list<int> */
    public array $selectedMods = [];

    /** @var list<int> */
    public array $selectedReforgerModIds = [];

    #[Computed]
    public function gameType(): GameType
    {
        return GameType::from($this->createGameType);
    }

    #[Computed]
    public function availableMods()
    {
        return WorkshopMod::query()
            ->forGame($this->gameType)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableReforgerMods()
    {
        return ReforgerMod::query()->orderBy('name')->get();
    }

    public function updatedCreateGameType(): void
    {
        $this->selectedMods = [];
        $this->selectedReforgerModIds = [];
        unset($this->availableMods, $this->availableReforgerMods, $this->gameType);
    }

    public function save(): void
    {
        $gameType = GameType::from($this->createGameType);

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mod_presets')->where('game_type', $gameType->value),
            ],
        ];

        if ($gameType === GameType::ArmaReforger) {
            $rules['selectedReforgerModIds'] = ['array'];
            $rules['selectedReforgerModIds.*'] = ['exists:reforger_mods,id'];
        } else {
            $rules['selectedMods'] = ['array'];
            $rules['selectedMods.*'] = ['exists:workshop_mods,id'];
        }

        $this->validate($rules);

        $preset = ModPreset::query()->create([
            'game_type' => $gameType,
            'name' => $this->name,
        ]);

        if ($gameType === GameType::ArmaReforger) {
            $preset->reforgerMods()->sync($this->selectedReforgerModIds);
            $modCount = count($this->selectedReforgerModIds);
        } else {
            $preset->mods()->sync($this->selectedMods);
            $modCount = count($this->selectedMods);
        }

        $this->auditLog("created preset '{$preset->name}' with {$modCount} mods");

        session()->flash('status', "Preset '{$this->name}' created successfully.");

        $this->redirect(route('presets.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Create Preset') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Create a named collection of mods.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-2xl">
        <flux:select wire:model.change="createGameType" :label="__('Game')">
            @foreach (\App\Enums\GameType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="name" :label="__('Preset Name')" required />

        @if ($this->gameType === \App\Enums\GameType::ArmaReforger)
            @include('pages.presets.partials.reforger-mod-fields', ['availableReforgerMods' => $this->availableReforgerMods])
        @else
            @include('pages.presets.partials.form-fields', ['availableMods' => $this->availableMods])
        @endif

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Create Preset') }}</flux:button>
            <flux:button :href="route('presets.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
