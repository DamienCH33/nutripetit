<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Service\Product\OrganicLabelDetector;
use PHPUnit\Framework\TestCase;

final class OrganicLabelDetectorTest extends TestCase
{
    private OrganicLabelDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new OrganicLabelDetector();
    }

    /**
     * @return array<string, array{list<string>, bool}>
     */
    public static function labelProvider(): array
    {
        return [
            'en:organic' => [['en:organic'], true],
            'en:eu-organic' => [['en:eu-organic'], true],
            'fr:ab-agriculture-biologique' => [['fr:ab-agriculture-biologique'], true],
            'fr:bio' => [['fr:bio'], true],
            'parmi plusieurs labels' => [['en:gluten-free', 'en:eu-organic'], true],
            'non bio' => [['en:gluten-free'], false],
            'faux positif biodégradable' => [['fr:biodegradable'], false],
            'liste vide' => [[], false],
        ];
    }

    /**
     * @param list<string> $labels
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('labelProvider')]
    public function testHasOrganicLabel(array $labels, bool $expected): void
    {
        self::assertSame($expected, $this->detector->hasOrganicLabel($labels));
    }

    public function testMalformedLabelsReturnFalse(): void
    {
        self::assertFalse($this->detector->hasOrganicLabel('en:organic'));
        self::assertFalse($this->detector->hasOrganicLabel(null));
    }

    public function testIsOrganicReadsProductRawData(): void
    {
        $product = new Product('3000000000270', 'Purée')
            ->setOffRawData(['labels_tags' => ['en:eu-organic']]);

        self::assertTrue($this->detector->isOrganic($product));
    }

    public function testIsOrganicFalseWithoutLabel(): void
    {
        $product = new Product('3000000000271', 'Chips')
            ->setOffRawData(['labels_tags' => ['en:vegan']]);

        self::assertFalse($this->detector->isOrganic($product));
    }
}
