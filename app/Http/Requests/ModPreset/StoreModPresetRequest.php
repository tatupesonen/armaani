<?php

namespace App\Http\Requests\ModPreset;

use App\GameManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModPresetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $gameType = $this->input('game_type');

        return [
            'game_type' => ['required', Rule::in(app(GameManager::class)->availableTypes())],
            'name' => ['required', 'string', 'max:255', Rule::unique('mod_presets')->where('game_type', $gameType)],
            'mod_ids' => ['nullable', 'array', 'max:500'],
            'mod_ids.*' => ['integer', 'exists:workshop_mods,id'],
            'reforger_mod_ids' => ['nullable', 'array', 'max:500'],
            'reforger_mod_ids.*' => ['integer', 'exists:reforger_mods,id'],
        ];
    }
}
