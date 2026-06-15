<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\Evaluator\PalmOilEvaluator;
use PHPUnit\Framework\TestCase;

final class PalmOilEvaluatorTest extends TestCase
{
    private PalmOilEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new PalmOilEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('palm_oil')));
        self::assertFalse($this->evaluator->supports($this->rule('added_sugars')));
    }

    public function testTriggersOnPalmOil(): void
    {
        $product = new Product('3000000000270', 'Pâte à tartiner')
            ->setIngredientsRaw('Sucre, huile de palme, noisettes');

        $applied = $this->evaluator->evaluate($product, $this->rule('palm_oil', -10), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(-10, $applied->pointsImpact);
        self::assertSame(RuleStatus::Triggered, $applied->status);
    }

    public function testSatisfiedWithoutPalmOil(): void
    {
        $product = new Product('3000000000271', 'Compote')
            ->setIngredientsRaw('Pommes, huile de colza');

        $applied = $this->evaluator->evaluate($product, $this->rule('palm_oil'), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(0, $applied->pointsImpact);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
    }

    public function testReturnsNullOnEmptyIngredients(): void
    {
        $product = new Product('3000000000272', 'Produit');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('palm_oil'), null));
    }

    private function rule(string $code, int $points = -10): ScoringRule
    {
        return new ScoringRule($code, 'Huile de palme', '', '1.0.0', $points, 'EFSA', 'https://example.test');
    }
}
