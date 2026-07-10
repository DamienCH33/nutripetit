<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\Entity\Product;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fige le 2e algorithme (v2.0.0) : base 60, plancher 50 (jamais "déconseillé"),
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
        // Aucune donnée -> aucun bonus/malus, score = base 60 ("Conforme").
        $result = $this->calculator->calculate(new Product('3000000000300', 'Lait 1er âge'));

        self::assertSame(60, $result->finalScore);
        self::assertSame('limit', $result->level);
        self::assertSame('infant_formula_2.0.0', $result->algoVersion);
    }

    public function testScoreNeverGoesBelowFloor(): void
    {
        // Cumule tous les malus : palme (-8) + sucres (-6) + protéines de soja (-4)
        // -> 60 - 18 = 42, borné au plancher 50.
        $product = new Product('3000000000301', 'Lait')
            ->setIngredientsRaw('huile de palme, sirop de glucose, maltodextrine, saccharose, protéines de soja')
            ->setNutriments([]);

        $result = $this->calculator->calculate($product);

        self::assertSame(50, $result->finalScore);
        self::assertNotSame('discouraged', $result->level);
    }

    public function testScoreIsCappedAt100(): void
    {
        // Cumule un max de bonus : DHA, ARA, sans palme, bio, GOS, probiotiques,
        // protéines basses, sodium bas -> bien au-delà de 100 avant clamp.
        $product = new Product('3000000000302', 'Lait premium bio')
            ->setIngredientsRaw('lactose, dha, ara, galacto-oligosaccharides, bifidobacterium')
            ->setNutriments(['proteins_100g' => 1.2, 'sodium_100g' => 0.02])
            ->setOffRawData(['labels_tags' => ['en:organic']]);

        $result = $this->calculator->calculate($product);

        self::assertSame(100, $result->finalScore);
        self::assertSame('ideal', $result->level);
    }

    public function testDhaBonusApplied(): void
    {
        $product = new Product('3000000000303', 'Lait')
            ->setIngredientsRaw('huile de palme, dha'); // palme -8, dha +5

        $result = $this->calculator->calculate($product);

        $codes = array_map(static fn ($r) => $r->ruleCode, $result->appliedRules);
        self::assertContains('formula_dha_present', $codes);
    }

    public function testPalmOilAsMainIngredientApplied(): void
    {
        $product = new Product('3000000000304', 'Lait')
            ->setIngredientsRaw('huile de palme, lactose, lactosérum');

        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertContains('formula_palm_oil_main', $codes);
    }

    public function testPreparedNutrimentsPreferred(): void
    {
        // proteins_prepared_100g (poudre reconstituée) prioritaire sur proteins_100g.
        $product = new Product('3000000000305', 'Lait poudre')
            ->setIngredientsRaw('lactose')
            ->setNutriments(['proteins_prepared_100g' => 1.3, 'proteins_100g' => 12]);

        $result = $this->calculator->calculate($product);

        $codes = array_map(static fn ($r) => $r->ruleCode, $result->appliedRules);
        self::assertContains('formula_low_protein', $codes);
    }

    public function testCaramelDoesNotTriggerAraBonus(): void
    {
        // Régression : « caramel » contient la sous-chaîne « ara ».
        $product = new Product('3000000000306', 'Lait de croissance')
            ->setIngredientsRaw('lait écrémé, lactose, caramel');

        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertNotContains('formula_ara_present', $codes);
    }

    public function testPreparationWordDoesNotTriggerAraBonus(): void
    {
        // Régression : « préparation » contient aussi la sous-chaîne « ara ».
        $product = new Product('3000000000307', 'Lait 1er âge')
            ->setIngredientsRaw('préparation à base de lait écrémé, lactose');

        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertNotContains('formula_ara_present', $codes);
    }

    public function testFructoseIsNotAPrebiotic(): void
    {
        // Régression : « fructose » (un sucre) matchait le bonus prébiotiques via « fructo ».
        $product = new Product('3000000000308', 'Lait')
            ->setIngredientsRaw('lait écrémé, lactose, fructose');

        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertNotContains('formula_prebiotics', $codes);
    }

    public function testSoyLecithinDoesNotTriggerSoyProteinMalus(): void
    {
        // Régression : la lécithine de soja (émulsifiant courant) n'est pas
        // une préparation à base de protéines de soja.
        $product = new Product('3000000000309', 'Lait 2ème âge')
            ->setIngredientsRaw('lait écrémé, lactose, lécithine de soja');

        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertNotContains('formula_soy_protein', $codes);
    }

    public function testSoyProteinBaseTriggersMalus(): void
    {
        $product = new Product('3000000000310', 'Lait végétal infantile')
            ->setIngredientsRaw('préparation à base de protéines de soja, huiles végétales');

        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            $this->calculator->calculate($product)->appliedRules,
        );

        self::assertContains('formula_soy_protein', $codes);
    }
}
