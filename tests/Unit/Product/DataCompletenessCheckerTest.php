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
        $product = new Product('3000000000002', 'Avec ingrédients')
            ->setIngredientsRaw('farine, eau, sucre');

        self::assertTrue($this->checker->hasSufficientData($product));
    }

    public function testNutrimentsOnlyIsSufficient(): void
    {
        $product = new Product('3000000000003', 'Avec nutriments')
            ->setNutriments(['energy-kcal_100g' => 250, 'proteins_100g' => 5.2]);

        self::assertTrue($this->checker->hasSufficientData($product));
    }

    public function testBlankIngredientsAndNonNumericNutrimentsAreInsufficient(): void
    {
        $product = new Product('3000000000004', 'Vide déguisé')
            ->setIngredientsRaw('   ')
            ->setNutriments(['nutrition-score' => 'unknown', 'grade' => '']);

        self::assertFalse($this->checker->hasSufficientData($product));
    }

    public function testNutrimentsWithoutIngredientsIsScoredButIncomplete(): void
    {
        // Cas réel : 3564706788480 (Leclerc « Mots d'enfants »), tableau
        // nutritionnel sur OFF mais aucune liste d'ingrédients.
        $product = new Product('3564706788480', 'Pommes de terre poulet et champignons')
            ->setNutriments(['energy-kcal_100g' => 62, 'sugars_100g' => 1.8]);

        self::assertTrue($this->checker->hasSufficientData($product));
        self::assertFalse($this->checker->isComplete($product));
    }

    public function testIngredientsAndNutrimentsIsComplete(): void
    {
        $product = new Product('3000000000005', 'Complet')
            ->setIngredientsRaw('carottes, eau')
            ->setNutriments(['energy-kcal_100g' => 30]);

        self::assertTrue($this->checker->isComplete($product));
    }
}
