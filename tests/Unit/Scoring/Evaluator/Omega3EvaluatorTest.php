<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\Omega3Evaluator;
use PHPUnit\Framework\TestCase;

final class Omega3EvaluatorTest extends TestCase
{
    private Omega3Evaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new Omega3Evaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        // Piège : le code de la règle est "omega3_rich", pas "omega_3".
        self::assertTrue($this->evaluator->supports($this->rule('omega3_rich')));
        self::assertFalse($this->evaluator->supports($this->rule('iron_rich')));
    }

    public function testTriggersAboveThreshold(): void
    {
        $product = new Product('3000000000150', 'Poisson gras')
            ->setNutriments(['omega-3_100g' => 0.005]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame('omega3_rich', $applied->ruleCode);
    }

    public function testDoesNotTriggerBelowThreshold(): void
    {
        $product = new Product('3000000000151', 'Produit')
            ->setNutriments(['omega-3_100g' => 0.0005]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule(), null));
    }

    public function testDoesNotTriggerWhenOmega3Missing(): void
    {
        $product = new Product('3000000000152', 'Produit');

        self::assertNull($this->evaluator->evaluate($product, $this->rule(), null));
    }

    private function rule(string $code = 'omega3_rich'): ScoringRule
    {
        $rule = new ScoringRule($code, 'Riche en oméga-3', '', '1.0.0', 10, 'ANSES', 'https://example.test');
        $rule->setThresholdValue(0.0012);

        return $rule;
    }
}
