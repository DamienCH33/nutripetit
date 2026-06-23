<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\SafeUrlExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SafeUrlExtensionTest extends TestCase
{
    private SafeUrlExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SafeUrlExtension();
    }

    #[DataProvider('provideSafeUrls')]
    public function testAllowsHttpAndHttps(string $url): void
    {
        self::assertSame($url, $this->extension->safeUrl($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSafeUrls(): iterable
    {
        yield 'https' => ['https://www.anses.fr/fr'];
        yield 'http' => ['http://www.oms.int'];
        yield 'https with path and query' => ['https://efsa.europa.eu/page?id=2&x=1'];
    }

    #[DataProvider('provideDangerousUrls')]
    public function testBlocksDangerousSchemes(string $url): void
    {
        self::assertSame('', $this->extension->safeUrl($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDangerousUrls(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript uppercase' => ['JavaScript:alert(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'relative without scheme' => ['//evil.example/x'];
        yield 'plain text' => ['not a url'];
    }

    public function testHandlesNullAndEmpty(): void
    {
        self::assertSame('', $this->extension->safeUrl(null));
        self::assertSame('', $this->extension->safeUrl(''));
        self::assertSame('', $this->extension->safeUrl('   '));
    }
}
