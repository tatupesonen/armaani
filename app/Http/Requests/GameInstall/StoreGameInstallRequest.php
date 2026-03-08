<?php

namespace App\Http\Requests\GameInstall;

use App\Enums\GameType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameInstallRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', Rule::in($gameType->branches())],
        ];
    }
}
