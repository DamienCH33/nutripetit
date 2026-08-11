<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\Evaluator\AddedSaltEvaluator;
use PHPUnit\Framework\TestCase;

final class AddedSaltEvaluatorTest extends TestCase
{
    private AddedSaltEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new AddedSaltEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule()));
        self::assertFalse($this->evaluator->supports($this->otherRule()));
    }

    public function testReturnsNullWhenSaltMissing(): void
    {
        $product = new Product('3000000000300', 'Purée')
            ->setNutriments(['energy-kcal_100g' => 60]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule(), null));
    }

    public function testSatisfiedUnderNppmLimitWithEnergy(): void
    {
        // 0,1 g sel/100 g -> 40 mg sodium/100 g ; à 100 kcal -> 40 mg/100 kcal <= 50
        $product = new Product('3000000000301', 'Purée')
            ->setNutriments(['salt_100g' => 0.1, 'energy-kcal_100g' => 100]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
        self::assertSame(0, $applied->pointsImpact);
    }

    public function testTriggeredOverNppmLimitWithEnergy(): void
    {
        // 0,5 g sel/100 g -> 200 mg sodium/100 g ; à 100 kcal -> 200 mg/100 kcal > 50
        $product = new Product('3000000000302', 'Plat salé')
            ->setNutriments(['salt_100g' => 0.5, 'energy-kcal_100g' => 100]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Triggered, $applied->status);
        self::assertSame(-15, $applied->pointsImpact);
    }

    public function testFallbackSatisfiedUnderMassThreshold(): void
    {
        // Pas d'énergie -> seuil massique du rule (0,3 g). 0,2 <= 0,3 -> Satisfied
        $product = new Product('3000000000303', 'Purée')
            ->setNutriments(['salt_100g' => 0.2]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
    }

    public function testFallbackTriggeredOverMassThreshold(): void
    {
        // Pas d'énergie -> seuil massique 0,3 g. 0,6 > 0,3 -> Triggered
        $product = new Product('3000000000304', 'Plat salé')
            ->setNutriments(['salt_100g' => 0.6]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Triggered, $applied->status);
        self::assertSame(-15, $applied->pointsImpact);
    }

    private function rule(): ScoringRule
    {
        return new ScoringRule('added_salt', 'Sel', '', '1.0.0', -15, 'OMS Europe NPPM 2022', 'https://example.test')
            ->setThresholdValue(0.3);
    }

    private function otherRule(): ScoringRule
    {
        return new ScoringRule('added_sugars', 'Sucres', '', '1.0.0', -25, 'OMS', 'https://example.test');
    }
}
