<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Service\Product\DataCompletenessChecker;
use PHPUnit\Framework\TestCase;

final class DataCompletenessCheckerTest extends TestCase
{
    private DataCompletenessChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new DataCompletenessChecker();
    }

    public function testEmptyProductIsInsufficient(): void
    {
        $product = new Product('3000000000001', 'Inconnu');

        self::assertFalse($this->checker->hasSufficientData($product));
    }

    public function testIngredientsOnlyIsSufficient(): void
    {
        $product = (new Product('3000000000002', 'Avec ingrédients'))
            ->setIngredientsRaw('farine, eau, sucre');

        self::assertTrue($this->checker->hasSufficientData($product));
    }

    public function testNutrimentsOnlyIsSufficient(): void
    {
        $product = (new Product('3000000000003', 'Avec nutriments'))
            ->setNutriments(['energy-kcal_100g' => 250, 'proteins_100g' => 5.2]);

        self::assertTrue($this->checker->hasSufficientData($product));
    }

    public function testBlankIngredientsAndNonNumericNutrimentsAreInsufficient(): void
    {
        $product = (new Product('3000000000004', 'Vide déguisé'))
            ->setIngredientsRaw('   ')
            ->setNutriments(['nutrition-score' => 'unknown', 'grade' => '']);

        self::assertFalse($this->checker->hasSufficientData($product));
    }
}
