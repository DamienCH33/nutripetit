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
        // Pas d'énergie -> plafond légal 0,5 g (directive 2006/125/CE). 0,2 <= 0,5 -> Satisfied
        $product = new Product('3000000000303', 'Purée')
            ->setNutriments(['salt_100g' => 0.2]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
    }

    public function testFallbackTriggeredOverMassThreshold(): void
    {
        // Pas d'énergie -> plafond légal 0,5 g. 0,6 > 0,5 -> Triggered
        $product = new Product('3000000000304', 'Plat salé')
            ->setNutriments(['salt_100g' => 0.6]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(RuleStatus::Triggered, $applied->status);
        self::assertSame(-15, $applied->pointsImpact);
    }

    public function testDairyGetsTheNppmDairyLimit(): void
    {
        // 0,2 g sel -> 80 mg sodium ; 100 kcal -> 80 mg/100 kcal : > 50 mais <= 100 (laitages)
        $yaourt = new Product('3000000000305', 'Yaourt nature bébé')
            ->setNutriments(['salt_100g' => 0.2, 'energy-kcal_100g' => 100])
            ->setOffRawData(['categories_tags' => ['en:dairies', 'en:baby-yogurts']]);
        $plat = new Product('3000000000306', 'Purée de légumes')
            ->setNutriments(['salt_100g' => 0.2, 'energy-kcal_100g' => 100]);

        self::assertSame(RuleStatus::Satisfied, $this->evaluator->evaluate($yaourt, $this->rule(), null)?->status);
        self::assertSame(RuleStatus::Triggered, $this->evaluator->evaluate($plat, $this->rule(), null)?->status);
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
