<?php

namespace App\Http\Requests\ModPreset;

use App\Enums\GameType;
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
        $gameType = GameType::from($this->input('game_type', 'arma3'));

        return [
            'game_type' => ['required', Rule::enum(GameType::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique('mod_presets')->where('game_type', $gameType->value)],
            'mod_ids' => ['nullable', 'array'],
            'mod_ids.*' => ['integer', 'exists:workshop_mods,id'],
            'reforger_mod_ids' => ['nullable', 'array'],
            'reforger_mod_ids.*' => ['integer', 'exists:reforger_mods,id'],
        ];
    }
}
