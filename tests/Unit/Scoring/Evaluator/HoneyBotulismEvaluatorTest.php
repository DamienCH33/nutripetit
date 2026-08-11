<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\Evaluator\HoneyBotulismEvaluator;
use PHPUnit\Framework\TestCase;

final class HoneyBotulismEvaluatorTest extends TestCase
{
    private HoneyBotulismEvaluator $evaluator;
    private ScoringRule $rule;

    protected function setUp(): void
    {
        $this->evaluator = new HoneyBotulismEvaluator();
        $this->rule = new ScoringRule(
            'honey_botulism',
            'Miel — risque botulisme avant 1 an',
            '',
            '1.0.0',
            -100,
            'ANSES',
            'https://www.anses.fr',
        );
    }

    public function testSupportsOnlyHoneyBotulism(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule));
        self::assertFalse($this->evaluator->supports(
            new ScoringRule('added_sugars', 'x', '', '1.0.0', -30, 'OMS', 'https://x.test'),
        ));
    }

    public function testTriggersOnHoneyWhenAgeUnknown(): void
    {
        $product = new Product('3000000000400', 'Compote')
            ->setIngredientsRaw('Bananes, miel, jus de citron.');
        $result = $this->evaluator->evaluate($product, $this->rule, null);

        self::assertNotNull($result);
        self::assertSame(RuleStatus::Triggered, $result->status);
        self::assertSame(-100, $result->pointsImpact);
    }

    public function testTriggersForBabyUnderTwelveMonths(): void
    {
        $product = new Product('3000000000401', 'Compote')
            ->setIngredientsRaw('Compote de pommes, miel.');
        $result = $this->evaluator->evaluate($product, $this->rule, 8);

        self::assertNotNull($result);
        self::assertSame(RuleStatus::Triggered, $result->status);
    }

    public function testDoesNotTriggerAtTwelveMonthsOrMore(): void
    {
        $product = new Product('3000000000402', 'Compote')
            ->setIngredientsRaw('Compote de pommes, miel.');
        self::assertNull($this->evaluator->evaluate($product, $this->rule, 12));
        self::assertNull($this->evaluator->evaluate($product, $this->rule, 24));
    }

    public function testReturnsNullWithoutHoney(): void
    {
        $product = new Product('3000000000403', 'Compote')
            ->setIngredientsRaw('Bananes, jus de citron.');
        self::assertNull($this->evaluator->evaluate($product, $this->rule, null));
    }

    public function testReturnsNullWithoutIngredients(): void
    {
        $product = new Product('3000000000404', 'Vide');
        self::assertNull($this->evaluator->evaluate($product, $this->rule, 8));
    }
}
