<?php

namespace App\Http\Requests\ModPreset;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModPresetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $modPreset = $this->route('modPreset');
        $gameType = $modPreset->game_type;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('mod_presets')->where('game_type', $gameType)->ignore($modPreset->id)],
            'mod_ids' => ['nullable', 'array', 'max:500'],
            'mod_ids.*' => ['integer', 'exists:workshop_mods,id'],
            'reforger_mod_ids' => ['nullable', 'array', 'max:500'],
            'reforger_mod_ids.*' => ['integer', 'exists:reforger_mods,id'],
        ];
    }
}
