<?php

namespace App\Http\Requests\WorkshopMod;

use App\Enums\GameType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'workshop_id' => ['required', 'integer', 'min:1'],
            'game_type' => ['nullable', Rule::enum(GameType::class)],
        ];
    }
}
