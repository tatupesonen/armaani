<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppSetting>
 */
class AppSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_webhook_url' => null,
        ];
    }

    public function withDiscordWebhook(): static
    {
        return $this->state(fn () => [
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1234567890/abcdefghijklmnop',
        ]);
    }
}
