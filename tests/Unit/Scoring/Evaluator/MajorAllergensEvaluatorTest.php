<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\MajorAllergensEvaluator;
use PHPUnit\Framework\TestCase;

final class MajorAllergensEvaluatorTest extends TestCase
{
    private MajorAllergensEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new MajorAllergensEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('major_allergens')));
        self::assertFalse($this->evaluator->supports($this->rule('soy_products')));
    }

    public function testTriggersAndTranslatesAllergens(): void
    {
        $product = (new Product('3000000000250', 'Yaourt'))
            ->setAllergens(['en:milk', 'en:nuts']);

        $applied = $this->evaluator->evaluate($product, $this->rule('major_allergens', -5), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertStringContainsString('Lait', $applied->reason);
        self::assertStringContainsString('Fruits à coque', $applied->reason);
    }

    public function testUnknownAllergenFallsBackToRawLabel(): void
    {
        $product = (new Product('3000000000251', 'Produit'))
            ->setAllergens(['en:kiwi']);

        $applied = $this->evaluator->evaluate($product, $this->rule('major_allergens'), null);

        self::assertNotNull($applied);
        self::assertStringContainsString('Kiwi', $applied->reason);
    }

    public function testDoesNotTriggerWithoutAllergens(): void
    {
        $product = (new Product('3000000000252', 'Compote'))->setAllergens([]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('major_allergens'), null));
    }

    private function rule(string $code, int $points = -5): ScoringRule
    {
        return new ScoringRule($code, 'Allergènes majeurs', '', '1.0.0', $points, 'INCO', 'https://example.test');
    }
}
