<?php

namespace App\Enums;

enum GameType: string
{
    case Arma3 = 'arma3';
    case ArmaReforger = 'reforger';
    case DayZ = 'dayz';

    /**
     * The default game type used as a fallback.
     */
    public static function default(): self
    {
        return self::Arma3;
    }

    /**
     * Human-readable display name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Arma3 => 'Arma 3',
            self::ArmaReforger => 'Arma Reforger',
            self::DayZ => 'DayZ',
        };
    }

    /**
     * Steam App ID for the dedicated server binary.
     */
    public function serverAppId(): int
    {
        return match ($this) {
            self::Arma3 => 233780,
            self::ArmaReforger => 1874900,
            self::DayZ => 223350,
        };
    }

    /**
     * Steam Game ID (used for workshop mod downloads).
     * For Reforger, this is the same as serverAppId.
     */
    public function gameId(): int
    {
        return match ($this) {
            self::Arma3 => 107410,
            self::ArmaReforger => 1874900,
            self::DayZ => 221100,
        };
    }

    /**
     * Server executable filename (no path).
     */
    public function binaryName(): string
    {
        return match ($this) {
            self::Arma3 => 'arma3server_x64',
            self::ArmaReforger => 'ArmaReforgerServer',
            self::DayZ => 'DayZServer_x64',
        };
    }

    /**
     * Default game port.
     */
    public function defaultPort(): int
    {
        return match ($this) {
            self::Arma3 => 2302,
            self::ArmaReforger => 2001,
            self::DayZ => 2302,
        };
    }

    /**
     * Default Steam query port.
     */
    public function defaultQueryPort(): int
    {
        return match ($this) {
            self::Arma3 => 2303,
            self::ArmaReforger => 17777,
            self::DayZ => 27016,
        };
    }

    /**
     * Available SteamCMD beta branches for this game.
     * Branches are hardcoded because the Steam API requires a Steamworks partner token.
     */
    public function branches(): array
    {
        return match ($this) {
            self::Arma3 => ['public', 'contact', 'creatordlc', 'profiling', 'performance', 'legacy'],
            self::ArmaReforger => ['public'],
            self::DayZ => ['public', 'experimental'],
        };
    }

    /**
     * Whether this game uses Steam Workshop mods downloaded via SteamCMD.
     * Reforger downloads its own mods at server startup via the server binary.
     */
    public function supportsWorkshopMods(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => true,
        };
    }

    /**
     * Whether this game supports headless clients for AI offloading.
     */
    public function supportsHeadlessClients(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => false,
        };
    }

    /**
     * Whether this game supports PBO mission file uploads.
     */
    public function supportsMissionUpload(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => false,
        };
    }

    /**
     * Whether mod files need to be converted to lowercase (Linux requirement).
     */
    public function requiresLowercaseConversion(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => true,
        };
    }

    /**
     * String to detect in server log indicating the server has fully booted.
     * Return null if no auto-detection is available.
     */
    public function bootDetectionString(): ?string
    {
        return match ($this) {
            self::Arma3 => 'Connected to Steam servers',
            self::ArmaReforger => null,
            self::DayZ => null,
        };
    }

    /**
     * File extension for the server profile config file, if applicable.
     */
    public function profileExtension(): ?string
    {
        return match ($this) {
            self::Arma3 => '.Arma3Profile',
            self::ArmaReforger => null,
            self::DayZ => null,
        };
    }

    /**
     * Map a Steam API consumer_appid to a GameType.
     * Used for auto-detecting which game a workshop mod belongs to.
     */
    public static function fromConsumerAppId(int $appId): ?self
    {
        return match ($appId) {
            107410 => self::Arma3,
            221100 => self::DayZ,
            1874900 => self::ArmaReforger,
            default => null,
        };
    }
}
