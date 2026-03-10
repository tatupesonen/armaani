<?php

namespace App\Services\Renderer;

use App\Contracts\ConfigRenderer;

class JsonConfigRenderer implements ConfigRenderer
{
    /**
     * Render configuration data as pretty-printed JSON.
     *
     * The template parameter is unused — JSON rendering is format-driven, not template-driven.
     *
     * @param  array<string, mixed>  $context
     */
    public function render(string $template, array $context = []): string
    {
        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }
}
