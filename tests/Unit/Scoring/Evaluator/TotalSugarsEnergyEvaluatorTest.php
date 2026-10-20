<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\Evaluator\TotalSugarsEnergyEvaluator;
use PHPUnit\Framework\TestCase;

final class TotalSugarsEnergyEvaluatorTest extends TestCase
{
    private TotalSugarsEnergyEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new TotalSugarsEnergyEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('total_sugars_energy')));
        self::assertFalse($this->evaluator->supports($this->rule('added_sugars')));
    }

    public function testTriggersAboveEnergyThreshold(): void
    {
        // 10g sucre × 4 kcal / 100 kcal = 40% énergie > 15%.
        $product = new Product('3000000000240', 'Dessert fruité')
            ->setNutriments(['sugars_100g' => 10, 'energy-kcal_100g' => 100]);

        $applied = $this->evaluator->evaluate($product, $this->rule('total_sugars_energy', -15), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertStringContainsString('40', $applied->reason);
        self::assertSame(-15, $applied->pointsImpact);
        self::assertSame(RuleStatus::Triggered, $applied->status);
    }

    public function testSatisfiedAtOrBelowThreshold(): void
    {
        // 3.75g × 4 / 100 = 15% : sous ou égal au seuil -> contrôle passé.
        $product = new Product('3000000000241', 'Purée légumes')
            ->setNutriments(['sugars_100g' => 3.75, 'energy-kcal_100g' => 100]);

        $applied = $this->evaluator->evaluate($product, $this->rule('total_sugars_energy'), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(0, $applied->pointsImpact);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
    }

    public function testHandlesStringValuesFromOff(): void
    {
        $product = new Product('3000000000242', 'Dessert')
            ->setNutriments(['sugars_100g' => '10', 'energy-kcal_100g' => '100']);

        $applied = $this->evaluator->evaluate($product, $this->rule('total_sugars_energy'), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Triggered, $applied->status);
    }

    public function testReturnsNullOnZeroEnergy(): void
    {
        $product = new Product('3000000000243', 'Eau aromatisée')
            ->setNutriments(['sugars_100g' => 5, 'energy-kcal_100g' => 0]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('total_sugars_energy'), null));
    }

    public function testReturnsNullWhenDataMissing(): void
    {
        $product = new Product('3000000000244', 'Produit')
            ->setNutriments(['sugars_100g' => 10]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('total_sugars_energy'), null));
    }

    /**
     * 20 % de l'énergie en sucres : trop pour un plat salé (15 %), normal pour
     * une compote (30 %) ou un yaourt (40 %). Référentiel OMS Europe NPPM, tableau 1.
     */
    public function testThresholdDependsOnProductType(): void
    {
        $nutriments = ['sugars_100g' => 5, 'energy-kcal_100g' => 100]; // 5 × 4 / 100 = 20 %
        $plat = new Product('3000000000400', 'Petit plat carottes riz')->setNutriments($nutriments)
            ->setOffRawData(['categories_tags' => ['en:baby-meals']]);
        $compote = new Product('3000000000401', 'Pomme poire')->setNutriments($nutriments)
            ->setOffRawData(['categories_tags' => ['en:compotes']]);
        $yaourt = new Product('3000000000402', 'Yaourt aux fruits')->setNutriments($nutriments)
            ->setOffRawData(['categories_tags' => ['en:baby-yogurts']]);

        $rule = $this->rule('total_sugars_energy');
        self::assertSame(RuleStatus::Triggered, $this->evaluator->evaluate($plat, $rule, 9)?->status);
        self::assertSame(RuleStatus::Satisfied, $this->evaluator->evaluate($compote, $rule, 9)?->status);
        self::assertSame(RuleStatus::Satisfied, $this->evaluator->evaluate($yaourt, $rule, 9)?->status);
    }

    public function testFruitPureeAboveThirtyPercentStillTriggers(): void
    {
        $compote = new Product('3000000000403', 'Compote pomme banane')
            ->setNutriments(['sugars_100g' => 14, 'energy-kcal_100g' => 70]); // 80 %

        self::assertSame(RuleStatus::Triggered, $this->evaluator->evaluate($compote, $this->rule('total_sugars_energy'), 9)?->status);
    }

    private function rule(string $code, int $points = -15): ScoringRule
    {
        return new ScoringRule($code, 'Sucres élevés', '', '1.0.0', $points, 'OMS Europe - NPPM', 'https://example.test');
    }
}
