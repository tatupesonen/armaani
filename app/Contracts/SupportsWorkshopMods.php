<?php

namespace App\Contracts;

/**
 * Capability interface for game handlers that use Steam Workshop mods
 * downloaded via SteamCMD.
 *
 * Handlers implementing this interface get default modSections(),
 * syncPresetMods(), and getPresetModCount() via the WorkshopModBehavior trait.
 */
interface SupportsWorkshopMods
{
    /**
     * Whether mod files need to be converted to lowercase (Linux requirement).
     * Some games (Arma 3, DayZ) require this for case-sensitive filesystems.
     */
    public function requiresLowercaseConversion(): bool;
}
