<?php

namespace App\Http\Requests\SteamSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveDiscordWebhookRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discord_webhook_url' => ['nullable', 'string', 'max:500', 'url:https'],
        ];
    }
}
