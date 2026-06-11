<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\Entity\Product;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fige le 2e algorithme : base 100, plancher 70 (jamais "déconseillé"),
 * plafond 100, bonus/malus spécifiques laits infantiles.
 */
final class InfantFormulaScoreCalculatorTest extends TestCase
{
    private InfantFormulaScoreCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new InfantFormulaScoreCalculator();
    }

    public function testEmptyFormulaStaysAtBase(): void
    {
        // Aucune donnée -> aucun bonus/malus, score = 100 plafonné.
        $result = $this->calculator->calculate(new Product('3000000000300', 'Lait 1er âge'));

        self::assertSame(100, $result->finalScore);
        self::assertSame('infant_formula_1.0.0', $result->algoVersion);
    }

    public function testScoreNeverGoesBelowFloor(): void
    {
        // Cumule tous les malus : palme + sucres + soja.
        $product = (new Product('3000000000301', 'Lait'))
            ->setIngredientsRaw('huile de palme, sirop de glucose, maltodextrine, saccharose, soja')
            ->setNutriments([]);

        $result = $this->calculator->calculate($product);

        self::assertGreaterThanOrEqual(70, $result->finalScore);
        self::assertNotSame('discouraged', $result->level);
    }

    public function testScoreIsCappedAt100(): void
    {
        // Cumule un max de bonus : DHA, ARA, sans palme, bio, GOS, probiotiques,
        // protéines basses, sodium bas -> bien au-delà de 100 avant clamp.
        $product = (new Product('3000000000302', 'Lait premium bio'))
            ->setIngredientsRaw('lactose, dha, ara, galacto-oligosaccharides, bifidobacterium')
            ->setNutriments(['proteins_100g' => 1.2, 'sodium_100g' => 0.02])
            ->setOffRawData(['labels_tags' => ['en:organic']]);

        $result = $this->calculator->calculate($product);

        self::assertSame(100, $result->finalScore);
        self::assertSame('ideal', $result->level);
    }

    public function testDhaBonusApplied(): void
    {
        $product = (new Product('3000000000303', 'Lait'))
            ->setIngredientsRaw('huile de palme, dha'); // palme -8, dha +5

        $result = $this->calculator->calculate($product);

        $codes = array_map(static fn($r) => $r->ruleCode, $result->appliedRules);
        self::assertContains('formula_dha_present', $codes);
    }

    public function testPalmOilAsMainIngredientApplied(): void
    {
        $product = (new Product('3000000000304', 'Lait'))
            ->setIngredientsRaw('huile de palme, lactose, lactosérum');

        $codes = array_map(
            static fn($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertContains('formula_palm_oil_main', $codes);
    }

    public function testPreparedNutrimentsPreferred(): void
    {
        // proteins_prepared_100g (poudre reconstituée) prioritaire sur proteins_100g.
        $product = (new Product('3000000000305', 'Lait poudre'))
            ->setIngredientsRaw('lactose')
            ->setNutriments(['proteins_prepared_100g' => 1.3, 'proteins_100g' => 12]);

        $result = $this->calculator->calculate($product);

        $codes = array_map(static fn($r) => $r->ruleCode, $result->appliedRules);
        self::assertContains('formula_low_protein', $codes);
    }
}
