<?php

namespace App\Http\Requests\WorkshopMod;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkshopModRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workshop_id' => ['required', 'numeric', 'min:1'],
            'game_type' => ['nullable', 'string'],
        ];
    }
}
