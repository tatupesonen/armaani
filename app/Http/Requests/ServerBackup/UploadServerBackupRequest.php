<?php

namespace App\Http\Requests\ServerBackup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadServerBackupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'backup_file' => ['required', 'file', 'max:10240'],
            'backup_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
