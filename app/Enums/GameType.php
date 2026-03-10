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
