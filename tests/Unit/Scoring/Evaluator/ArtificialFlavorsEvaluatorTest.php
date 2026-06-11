<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\ArtificialFlavorsEvaluator;
use PHPUnit\Framework\TestCase;

final class ArtificialFlavorsEvaluatorTest extends TestCase
{
    private ArtificialFlavorsEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ArtificialFlavorsEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('artificial_flavors')));
        self::assertFalse($this->evaluator->supports($this->rule('added_salt')));
    }

    public function testTriggersOnArtificialFlavorKeyword(): void
    {
        $product = (new Product('3000000000100', 'Dessert'))
            ->setIngredientsRaw('Sucre, eau, arôme synthétique');

        $applied = $this->evaluator->evaluate($product, $this->rule('artificial_flavors', -15), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame('artificial_flavors', $applied->ruleCode);
        self::assertSame(-15, $applied->pointsImpact);
    }

    public function testDoesNotTriggerOnNaturalFlavor(): void
    {
        $product = (new Product('3000000000101', 'Compote'))
            ->setIngredientsRaw('Pommes, arôme naturel de vanille');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('artificial_flavors'), null));
    }

    public function testDoesNotTriggerWithoutIngredients(): void
    {
        $product = new Product('3000000000102', 'Produit');

        self::assertNull($this->evaluator->evaluate($product, $this->rule('artificial_flavors'), null));
    }

    private function rule(string $code, int $points = -15): ScoringRule
    {
        return new ScoringRule($code, 'Arômes artificiels', '', '1.0.0', $points, 'PNNS 4', 'https://example.test');
    }
}
