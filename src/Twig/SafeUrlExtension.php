<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtre Twig `safe_url` : n'autorise que les URLs http(s).
 */
final class SafeUrlExtension extends AbstractExtension
{
    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_url', $this->safeUrl(...)),
        ];
    }

    public function safeUrl(?string $url): string
    {
        if (null === $url) {
            return '';
        }

        $url = trim($url);
        if ('' === $url) {
            return '';
        }

        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if (!\is_string($scheme)) {
            return '';
        }

        return \in_array(strtolower($scheme), ['http', 'https'], true) ? $url : '';
    }
}
