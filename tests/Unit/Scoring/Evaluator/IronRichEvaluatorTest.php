<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\IronRichEvaluator;
use PHPUnit\Framework\TestCase;

final class IronRichEvaluatorTest extends TestCase
{
    private IronRichEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new IronRichEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('iron_rich')));
        self::assertFalse($this->evaluator->supports($this->rule('omega3_rich')));
    }

    public function testTriggersAboveThreshold(): void
    {
        $product = new Product('3000000000140', 'Céréales fer')
            ->setNutriments(['iron_100g' => 0.005]);

        $applied = $this->evaluator->evaluate($product, $this->rule(), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame('iron_rich', $applied->ruleCode);
    }

    public function testDoesNotTriggerBelowThreshold(): void
    {
        $product = new Product('3000000000141', 'Produit')
            ->setNutriments(['iron_100g' => 0.0005]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule(), null));
    }

    public function testDoesNotTriggerWhenIronMissing(): void
    {
        $product = new Product('3000000000142', 'Produit');

        self::assertNull($this->evaluator->evaluate($product, $this->rule(), null));
    }

    private function rule(string $code = 'iron_rich'): ScoringRule
    {
        $rule = new ScoringRule($code, 'Riche en fer', '', '1.0.0', 10, 'ANSES', 'https://example.test');
        $rule->setThresholdValue(0.0012);

        return $rule;
    }
}
