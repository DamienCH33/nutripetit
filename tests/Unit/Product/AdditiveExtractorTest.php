<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Service\Product\AdditiveExtractor;
use PHPUnit\Framework\TestCase;

final class AdditiveExtractorTest extends TestCase
{
    public function testEachAdditiveCarriesAStatusAndANoteForTheDetailView(): void
    {
        $product = new Product('3000000000000', 'Produit test')
            ->setAdditives(['en:e306', 'en:e171', 'en:e955', 'en:e306']);

        $additives = new AdditiveExtractor()->extractAdditives($product);

        self::assertSame(['E306', 'E171', 'E955'], array_column($additives, 'code'));
        self::assertSame(
            [AdditiveExtractor::STATUS_AUTHORIZED, AdditiveExtractor::STATUS_CONTROVERSIAL, AdditiveExtractor::STATUS_SWEETENER],
            array_column($additives, 'status'),
        );
        self::assertSame('Tocophérols (Vitamine E)', $additives[0]['name']);
        self::assertStringContainsString('EFSA', $additives[1]['note']);
        self::assertStringContainsString('1333/2008', $additives[2]['note']);
    }
}
