<?php

namespace App\Http\Requests\SteamSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mod_download_batch_size' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
