<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\Evaluator\ControversialAdditivesEvaluator;
use PHPUnit\Framework\TestCase;

final class ControversialAdditivesEvaluatorTest extends TestCase
{
    private ControversialAdditivesEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ControversialAdditivesEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('controversial_additives')));
        self::assertFalse($this->evaluator->supports($this->rule('added_sugars')));
    }

    public function testTriggersOnControversialECode(): void
    {
        $product = new Product('3000000000220', 'Bonbons')
            ->setAdditives(['en:e171', 'en:e330']);

        $applied = $this->evaluator->evaluate($product, $this->rule('controversial_additives', -30), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertStringContainsString('E171', $applied->reason);
        self::assertSame(RuleStatus::Triggered, $applied->status);
    }

    public function testSatisfiedOnHarmlessAdditives(): void
    {
        // E330 = acide citrique, anodin.
        $product = new Product('3000000000221', 'Compote')
            ->setAdditives(['en:e330']);

        $applied = $this->evaluator->evaluate($product, $this->rule('controversial_additives'), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertSame(0, $applied->pointsImpact);
        self::assertSame(RuleStatus::Satisfied, $applied->status);
    }

    public function testReturnsNullWithoutAdditives(): void
    {
        $product = new Product('3000000000222', 'Purée')->setAdditives([]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('controversial_additives'), null));
    }

    private function rule(string $code, int $points = -30): ScoringRule
    {
        return new ScoringRule($code, 'Additifs controversés', '', '1.0.0', $points, 'EFSA', 'https://example.test');
    }
}
