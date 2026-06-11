<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\SoyProductsEvaluator;
use PHPUnit\Framework\TestCase;

final class SoyProductsEvaluatorTest extends TestCase
{
    private SoyProductsEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new SoyProductsEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('soy_products')));
        self::assertFalse($this->evaluator->supports($this->rule('added_sugars')));
    }

    public function testTriggersOnSoyKeyword(): void
    {
        $product = (new Product('3000000000130', 'Dessert végétal'))
            ->setIngredientsRaw('Tofu, eau, sel');

        $applied = $this->evaluator->evaluate($product, $this->rule('soy_products', -10), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame('soy_products', $applied->ruleCode);
    }

    public function testDoesNotTriggerWithoutSoy(): void
    {
        $product = (new Product('3000000000131', 'Compote'))
            ->setIngredientsRaw('Pommes, poires');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('soy_products'), null));
    }

    private function rule(string $code, int $points = -10): ScoringRule
    {
        return new ScoringRule($code, 'Soja', '', '1.0.0', $points, 'ANSES', 'https://example.test');
    }
}
