<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\ChokingHazardEvaluator;
use PHPUnit\Framework\TestCase;

final class ChokingHazardEvaluatorTest extends TestCase
{
    private ChokingHazardEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ChokingHazardEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('choking_hazard')));
        self::assertFalse($this->evaluator->supports($this->rule('soy_products')));
    }

    public function testTriggersOnHazardKeyword(): void
    {
        $product = new Product('3000000000110', 'Mélange')
            ->setIngredientsRaw('Flocons d\'avoine, raisins secs');

        $applied = $this->evaluator->evaluate($product, $this->rule('choking_hazard', -20), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame('choking_hazard', $applied->ruleCode);
    }

    public function testDoesNotTriggerOnSafeIngredients(): void
    {
        $product = new Product('3000000000111', 'Purée')
            ->setIngredientsRaw('Carottes, pommes de terre');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('choking_hazard'), null));
    }

    private function rule(string $code, int $points = -20): ScoringRule
    {
        return new ScoringRule($code, 'Risque d\'étouffement', '', '1.0.0', $points, 'ANSES', 'https://example.test');
    }
}
