<?php

declare(strict_types=1);

namespace App\Tests\Service\Scoring\Evaluator;

use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\ChokingHazardEvaluator;
use PHPUnit\Framework\TestCase;

final class ChokingHazardEvaluatorTest extends TestCase
{
    private function rule(): ScoringRule
    {
        $rule = $this->createMock(ScoringRule::class);
        $rule->method('getCode')->willReturn('choking_hazard');
        $rule->method('getLabel')->willReturn('Risque d\'étouffement');
        $rule->method('getPointsImpact')->willReturn(-30);
        $rule->method('getSourceName')->willReturn('ANSES Avis 0-3 ans (2019)');
        $rule->method('getSourceUrl')->willReturn('https://www.anses.fr');

        return $rule;
    }

    private function productWith(?string $ingredients): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getIngredientsRaw')->willReturn($ingredients);

        return $product;
    }

    public function testSupportsOnlyItsCode(): void
    {
        $evaluator = new ChokingHazardEvaluator();
        self::assertTrue($evaluator->supports($this->rule()));

        $other = $this->createMock(ScoringRule::class);
        $other->method('getCode')->willReturn('added_sugars');
        self::assertFalse($evaluator->supports($other));
    }

    public function testTriggersWhenHazardPresent(): void
    {
        $evaluator = new ChokingHazardEvaluator();
        $result = $evaluator->evaluate(
            $this->productWith('Purée, raisins entiers, sucre'),
            $this->rule(),
            12,
        );

        self::assertNotNull($result);
        self::assertSame(-30, $result->pointsImpact);
        self::assertStringContainsString('étouffement', $result->reason);
    }

    public function testDoesNotTriggerWhenSafe(): void
    {
        $evaluator = new ChokingHazardEvaluator();
        self::assertNull($evaluator->evaluate(
            $this->productWith('Carotte, eau, pomme'),
            $this->rule(),
            12,
        ));
    }

    public function testDoesNotTriggerWhenNoIngredients(): void
    {
        $evaluator = new ChokingHazardEvaluator();
        self::assertNull($evaluator->evaluate($this->productWith(null), $this->rule(), 12));
    }
}
