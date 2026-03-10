<?php

namespace App\Contracts;

interface ConfigRenderer
{
    /**
     * Render configuration content to a string.
     *
     * @param  string  $template  The template name or identifier.
     * @param  array<string, mixed>  $context  The data to render.
     */
    public function render(string $template, array $context = []): string;
}
