<?php

namespace App\Http\Requests\ReforgerMod;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReforgerModRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mod_id' => ['required', 'string', 'unique:reforger_mods,mod_id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
