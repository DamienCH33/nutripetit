<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\BabyFoodCertifiedEvaluator;
use PHPUnit\Framework\TestCase;

final class BabyFoodCertifiedEvaluatorTest extends TestCase
{
    private BabyFoodCertifiedEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new BabyFoodCertifiedEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('baby_food_certified')));
        self::assertFalse($this->evaluator->supports($this->rule('organic_certified')));
    }

    public function testTriggersOnOffCategory(): void
    {
        $product = (new Product('3000000000210', 'Petit pot carottes'))
            ->setOffRawData(['categories_tags' => ['en:baby-foods', 'en:purees']]);

        $applied = $this->evaluator->evaluate($product, $this->rule('baby_food_certified', 10), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(10, $applied->pointsImpact);
    }

    public function testTriggersOnNameKeywordFallback(): void
    {
        $product = (new Product('3000000000211', 'Purée pommes dès 6 mois'))
            ->setOffRawData([]);

        self::assertNotNull($this->evaluator->evaluate($product, $this->rule('baby_food_certified'), null));
    }

    public function testRobustToMalformedOffData(): void
    {
        // categories_tags non-array et entrées non-string ne doivent pas crasher.
        $product = (new Product('3000000000212', 'Yaourt'))
            ->setOffRawData(['categories_tags' => 'en:baby-foods']);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('baby_food_certified'), null));
    }

    public function testDoesNotTriggerOnAdultProduct(): void
    {
        $product = (new Product('3000000000213', 'Chips paprika'))
            ->setOffRawData(['categories_tags' => ['en:snacks']]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('baby_food_certified'), null));
    }

    private function rule(string $code, int $points = 10): ScoringRule
    {
        return new ScoringRule($code, 'Aliment bébé certifié', '', '1.0.0', $points, 'UE', 'https://example.test');
    }
}
