<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Service\Product\AllergenExtractor;
use PHPUnit\Framework\TestCase;

final class AllergenExtractorTest extends TestCase
{
    private AllergenExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new AllergenExtractor();
    }

    public function testUnknownWithoutIngredients(): void
    {
        // Jamais de faux « sans allergène » : sans ingrédients, on ne sait pas.
        $product = new Product('3000000000420', 'Produit')->setAllergens([]);

        self::assertNull($this->extractor->extract($product));
    }

    public function testDeclaredAndTraces(): void
    {
        $product = new Product('3000000000421', 'Biscuit')
            ->setIngredientsRaw('Farine de blé, lait, œufs')
            ->setAllergens(['en:gluten', 'en:milk', 'en:eggs', 'en:milk'])
            ->setOffRawData(['traces_tags' => ['en:nuts']]);

        self::assertSame([
            'declared' => [
                ['code' => 'gluten', 'label' => 'Gluten'],
                ['code' => 'milk', 'label' => 'Lait'],
                ['code' => 'eggs', 'label' => 'Œufs'],
            ],
            'traces' => [['code' => 'nuts', 'label' => 'Fruits à coque']],
        ], $this->extractor->extract($product));
    }

    public function testNoAllergenWithIngredients(): void
    {
        $product = new Product('3000000000422', 'Compote')->setIngredientsRaw('Pommes');

        self::assertSame(['declared' => [], 'traces' => []], $this->extractor->extract($product));
    }

    public function testUnknownTagFallsBackToCode(): void
    {
        $product = new Product('3000000000423', 'Produit')->setAllergens(['en:kiwi', 'fr:lait']);

        self::assertSame(['Kiwi', 'Lait'], $this->extractor->declaredLabels($product));
    }
}
