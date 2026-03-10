<?php

namespace App\Services\Renderer;

use App\Contracts\ConfigRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class TwigConfigRenderer implements ConfigRenderer
{
    protected Environment $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader(resource_path('templates/configs'));

        $this->twig = new Environment($loader, [
            'autoescape' => false,
            'strict_variables' => true,
            'cache' => storage_path('framework/twig'),
        ]);

        $this->twig->addFilter(new TwigFilter('format_decimal', function (string|float $value): string {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }));
    }

    /**
     * Render a Twig config template with the given context variables.
     *
     * @param  array<string, mixed>  $context
     */
    public function render(string $template, array $context = []): string
    {
        return rtrim($this->twig->render($template, $context), "\n")."\n";
    }
}
