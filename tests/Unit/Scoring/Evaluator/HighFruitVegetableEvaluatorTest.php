<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\HighFruitVegetableEvaluator;
use PHPUnit\Framework\TestCase;

final class HighFruitVegetableEvaluatorTest extends TestCase
{
    private HighFruitVegetableEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new HighFruitVegetableEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('high_fruit_vegetable')));
        self::assertFalse($this->evaluator->supports($this->rule('organic_certified')));
    }

    public function testTriggersAt50PercentBoundary(): void
    {
        // Le code exige >= 50 : la borne exacte doit déclencher.
        $product = (new Product('3000000000240', 'Purée'))
            ->setNutriments(['fruits-vegetables-legumes-estimate-from-ingredients_100g' => 50.0]);

        $applied = $this->evaluator->evaluate($product, $this->rule('high_fruit_vegetable', 10), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
    }

    public function testUsesFallbackNutrimentKey(): void
    {
        $product = (new Product('3000000000241', 'Purée'))
            ->setNutriments(['fruits-vegetables-nuts-estimate-from-ingredients_100g' => 80]);

        self::assertNotNull($this->evaluator->evaluate($product, $this->rule('high_fruit_vegetable'), null));
    }

    public function testDoesNotTriggerBelow50(): void
    {
        $product = (new Product('3000000000242', 'Biscuit'))
            ->setNutriments(['fruits-vegetables-legumes-estimate-from-ingredients_100g' => 49.9]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('high_fruit_vegetable'), null));
    }

    public function testDoesNotTriggerWhenEstimateMissing(): void
    {
        $product = (new Product('3000000000243', 'Produit'))->setNutriments([]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('high_fruit_vegetable'), null));
    }

    private function rule(string $code, int $points = 10): ScoringRule
    {
        return new ScoringRule($code, 'Riche en fruits et légumes', '', '1.0.0', $points, 'PNNS', 'https://example.test');
    }
}
