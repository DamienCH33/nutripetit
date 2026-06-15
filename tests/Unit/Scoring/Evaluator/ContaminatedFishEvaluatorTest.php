<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\Evaluator\ContaminatedFishEvaluator;
use PHPUnit\Framework\TestCase;

final class ContaminatedFishEvaluatorTest extends TestCase
{
    private ContaminatedFishEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ContaminatedFishEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('contaminated_fish')));
        self::assertFalse($this->evaluator->supports($this->rule('iron_rich')));
    }

    public function testTriggersOnRiskyFish(): void
    {
        $product = new Product('3000000000120', 'Plat poisson')
            ->setIngredientsRaw('Espadon, eau, sel');

        $applied = $this->evaluator->evaluate($product, $this->rule('contaminated_fish', -25), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame('contaminated_fish', $applied->ruleCode);
        self::assertSame(RuleStatus::Triggered, $applied->status);
    }

    public function testSatisfiedOnSafeFish(): void
    {
        $product = new Product('3000000000121', 'Plat poisson')
            ->setIngredientsRaw('Cabillaud, eau, sel');

        $applied = $this->evaluator->evaluate($product, $this->rule('contaminated_fish'), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(0, $applied->pointsImpact);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
    }

    public function testReturnsNullOnEmptyIngredients(): void
    {
        $product = new Product('3000000000122', 'Produit');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('contaminated_fish'), null));
    }

    private function rule(string $code, int $points = -25): ScoringRule
    {
        return new ScoringRule($code, 'Poisson à risque', '', '1.0.0', $points, 'ANSES', 'https://example.test');
    }
}
