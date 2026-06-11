<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\OrganicCertifiedEvaluator;
use PHPUnit\Framework\TestCase;

final class OrganicCertifiedEvaluatorTest extends TestCase
{
    private OrganicCertifiedEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new OrganicCertifiedEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('organic_certified')));
        self::assertFalse($this->evaluator->supports($this->rule('baby_food_certified')));
    }

    public function testTriggersOnOffLabel(): void
    {
        $product = (new Product('3000000000260', 'Purée'))
            ->setOffRawData(['labels_tags' => ['en:organic']]);

        $applied = $this->evaluator->evaluate($product, $this->rule('organic_certified', 10), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
    }

    public function testTriggersOnNameFallback(): void
    {
        $product = (new Product('3000000000261', 'Compote pomme bio'))
            ->setOffRawData([]);

        self::assertNotNull($this->evaluator->evaluate($product, $this->rule('organic_certified'), null));
    }

    public function testRobustToMalformedLabels(): void
    {
        $product = (new Product('3000000000262', 'Yaourt'))
            ->setOffRawData(['labels_tags' => 'en:organic']);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('organic_certified'), null));
    }

    public function testDoesNotTriggerOnNonOrganic(): void
    {
        $product = (new Product('3000000000263', 'Chips'))
            ->setOffRawData(['labels_tags' => ['en:gluten-free']]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('organic_certified'), null));
    }

    private function rule(string $code, int $points = 10): ScoringRule
    {
        return new ScoringRule($code, 'Certifié bio', '', '1.0.0', $points, 'UE 2018/848', 'https://example.test');
    }
}
