<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\AddedSugarsEvaluator;
use PHPUnit\Framework\TestCase;

final class AddedSugarsEvaluatorTest extends TestCase
{
    private AddedSugarsEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new AddedSugarsEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('added_sugars')));
        self::assertFalse($this->evaluator->supports($this->rule('sweeteners')));
    }

    public function testTriggersOnSugarKeyword(): void
    {
        $product = new Product('3000000000200', 'Biscuit')
            ->setIngredientsRaw('Farine, sirop de glucose, beurre');

        $applied = $this->evaluator->evaluate($product, $this->rule('added_sugars', -25), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(-25, $applied->pointsImpact);
        self::assertStringContainsString('sirop de glucose', $applied->reason);
    }

    public function testCaseInsensitive(): void
    {
        $product = new Product('3000000000201', 'Biscuit')
            ->setIngredientsRaw('Farine, SUCRE de canne');

        self::assertNotNull($this->evaluator->evaluate($product, $this->rule('added_sugars'), null));
    }

    public function testDoesNotTriggerWithoutSugar(): void
    {
        $product = new Product('3000000000202', 'Purée')
            ->setIngredientsRaw('Carottes, eau');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('added_sugars'), null));
    }

    public function testDoesNotTriggerOnEmptyIngredients(): void
    {
        $product = new Product('3000000000203', 'Produit')->setIngredientsRaw('   ');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('added_sugars'), null));
    }

    private function rule(string $code, int $points = -25): ScoringRule
    {
        return new ScoringRule($code, 'Sucres ajoutés', '', '1.0.0', $points, 'OMS', 'https://example.test');
    }
}
