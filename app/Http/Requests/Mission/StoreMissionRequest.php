<?php

namespace App\Http\Requests\Mission;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'missions' => ['required', 'array'],
            'missions.*' => ['required', 'file', 'max:524288'],
        ];
    }
}
