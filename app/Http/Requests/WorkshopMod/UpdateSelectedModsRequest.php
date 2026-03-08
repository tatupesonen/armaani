<?php

namespace App\Http\Requests\WorkshopMod;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSelectedModsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mod_ids' => ['required', 'array', 'min:1'],
            'mod_ids.*' => ['integer', 'exists:workshop_mods,id'],
        ];
    }
}
