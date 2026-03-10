<?php

namespace App\Contracts;

interface DetectsServerState
{
    /**
     * Strings that appear in server log when the server is fully booted.
     * Return an empty array to skip auto-detection.
     *
     * @return array<int, string>
     */
    public function getBootDetectionStrings(): array;

    /**
     * Strings that appear in server log when the server has crashed.
     * Return an empty array to skip auto-detection of crashes.
     *
     * @return array<int, string>
     */
    public function getCrashDetectionStrings(): array;

    /**
     * String that appears in server log when the server begins downloading mods.
     * Return null if this game does not download mods at startup.
     */
    public function getModDownloadStartedString(): ?string;

    /**
     * String that appears in server log when the server finishes downloading mods.
     * Return null if this game does not download mods at startup.
     */
    public function getModDownloadFinishedString(): ?string;
}
