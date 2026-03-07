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

new #[Title('Edit Preset')] class extends Component
{
    use AuditsActions;

    public ModPreset $modPreset;

    public string $name = '';

    /** @var list<int> */
    public array $selectedMods = [];

    /** @var list<int> */
    public array $selectedReforgerModIds = [];

    #[Computed]
    public function availableMods()
    {
        return WorkshopMod::query()
            ->forGame($this->modPreset->game_type)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableReforgerMods()
    {
        return ReforgerMod::query()->orderBy('name')->get();
    }

    public function mount(ModPreset $modPreset): void
    {
        $this->modPreset = $modPreset;
        $this->name = $modPreset->name;

        if ($modPreset->game_type === GameType::ArmaReforger) {
            $this->selectedReforgerModIds = $modPreset->reforgerMods()->pluck('reforger_mods.id')->all();
        } else {
            $this->selectedMods = $modPreset->mods()->pluck('workshop_mods.id')->all();
        }
    }

    public function save(): void
    {
        $gameType = $this->modPreset->game_type;

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mod_presets')
                    ->where('game_type', $gameType->value)
                    ->ignore($this->modPreset->id),
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

        $this->modPreset->update(['name' => $this->name]);

        if ($gameType === GameType::ArmaReforger) {
            $this->modPreset->reforgerMods()->sync($this->selectedReforgerModIds);
            $modCount = count($this->selectedReforgerModIds);
        } else {
            $this->modPreset->mods()->sync($this->selectedMods);
            $modCount = count($this->selectedMods);
        }

        $this->auditLog("updated preset '{$this->name}' with {$modCount} mods");

        session()->flash('status', "Preset '{$this->name}' updated successfully.");

        $this->redirect(route('presets.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <flux:heading size="xl">{{ __('Edit Preset') }}: {{ $modPreset->name }}</flux:heading>
            <flux:badge variant="secondary" size="sm">{{ $modPreset->game_type->label() }}</flux:badge>
        </div>
        <flux:text class="mt-2">{{ __('Update the preset name and mod selection.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-2xl">
        <flux:input wire:model="name" :label="__('Preset Name')" required />

        @if ($modPreset->game_type === \App\Enums\GameType::ArmaReforger)
            @include('pages.presets.partials.reforger-mod-fields', ['availableReforgerMods' => $this->availableReforgerMods])
        @else
            @include('pages.presets.partials.form-fields', ['availableMods' => $this->availableMods])
        @endif

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Update Preset') }}</flux:button>
            <flux:button :href="route('presets.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
