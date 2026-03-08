<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /** @use HasFactory<\Database\Factories\AppSettingFactory> */
    use HasFactory;

    /**
     * Get the current (singleton) AppSetting row, creating one if needed.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    protected $fillable = [
        'discord_webhook_url',
    ];

    protected $hidden = [
        'discord_webhook_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discord_webhook_url' => 'encrypted',
        ];
    }
}
