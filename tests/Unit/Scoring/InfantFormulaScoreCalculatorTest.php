<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\Entity\Product;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fige l'algo laits v3.1.0 : base 60, plancher 60 (jamais "déconseillé"),
 * DHA retiré (obligatoire depuis 2020, non discriminant), ARA en BONUS
 * (facultatif dans 2016/127), macronutriments chiffrés NON scorés (données
 * OFF poudre/reconstitué non fiables).
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
        $result = $this->calculator->calculate(new Product('3000000000300', 'Lait 1er âge'));

        self::assertSame(60, $result->finalScore);
        self::assertSame('limit', $result->level);
        self::assertSame('infant_formula_3.0.0', $result->algoVersion);
    }

    public function testScoreNeverGoesBelowFloor(): void
    {
        // Cumule les malus : palme 1er (-8) + sucres (-6) + soja (-4) + arômes (-4)
        // → borné au plancher 60.
        $product = new Product('3000000000301', 'Lait')
            ->setIngredientsRaw('huile de palme, saccharose, protéines de soja, arôme vanille');

        $result = $this->calculator->calculate($product);

        self::assertSame(60, $result->finalScore);
        self::assertNotSame('discouraged', $result->level);
    }

    public function testDhaNoLongerBonified(): void
    {
        // DHA obligatoire depuis 2020 → ne doit PLUS être un bonus.
        $product = new Product('3000000000303', 'Lait')
            ->setIngredientsRaw('lactose, dha');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertNotContains('formula_dha_present', $codes);
    }

    public function testAraIsNowABonus(): void
    {
        // ARA facultatif dans 2016/127 → bonus discriminant.
        $product = new Product('3000000000311', 'Lait premium')
            ->setIngredientsRaw('lactose, acide arachidonique (ara)');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertContains('formula_ara_present', $codes);
    }

    public function testPalmOilAsMainIngredientApplied(): void
    {
        $product = new Product('3000000000304', 'Lait')
            ->setIngredientsRaw('huile de palme, lactose, lactosérum');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertContains('formula_palm_oil_main', $codes);
    }

    // --- Régressions de sous-chaîne (déjà connues, à conserver) ---

    public function testCaramelDoesNotTriggerAraBonus(): void
    {
        $product = new Product('3000000000306', 'Lait de croissance')
            ->setIngredientsRaw('lait écrémé, lactose, caramel');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertNotContains('formula_ara_present', $codes);
    }

    public function testPreparationWordDoesNotTriggerAraBonus(): void
    {
        $product = new Product('3000000000307', 'Lait 1er âge')
            ->setIngredientsRaw('préparation à base de lait écrémé, lactose');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertNotContains('formula_ara_present', $codes);
    }

    public function testFructoseIsNotAPrebiotic(): void
    {
        $product = new Product('3000000000308', 'Lait')
            ->setIngredientsRaw('lait écrémé, lactose, fructose');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertNotContains('formula_prebiotics', $codes);
    }

    public function testSoyLecithinDoesNotTriggerSoyProteinMalus(): void
    {
        $product = new Product('3000000000309', 'Lait 2ème âge')
            ->setIngredientsRaw('lait écrémé, lactose, lécithine de soja');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertNotContains('formula_soy_protein', $codes);
    }

    public function testSoyProteinBaseTriggersMalus(): void
    {
        $product = new Product('3000000000310', 'Lait végétal infantile')
            ->setIngredientsRaw('préparation à base de protéines de soja, huiles végétales');

        $codes = array_map(static fn ($r) => $r->ruleCode, $this->calculator->calculate($product)->appliedRules);
        self::assertContains('formula_soy_protein', $codes);
    }
}
