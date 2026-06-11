<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\DataFixtures\ScoringRuleFixtures;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;
use App\Service\Scoring\ScoreCalculator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou : chaque règle active doit avoir au moins un evaluator.
 */
final class RuleEvaluatorCoverageTest extends KernelTestCase
{
    public function testEveryActiveRuleHasAnEvaluator(): void
    {
        self::bootKernel();

        $calculator = self::getContainer()->get(ScoreCalculator::class);
        $property = new \ReflectionProperty(ScoreCalculator::class, 'evaluators');
        /** @var list<RuleEvaluator> $evaluators */
        $evaluators = iterator_to_array($property->getValue($calculator), false);

        $missing = [];
        foreach (ScoringRuleFixtures::getRules() as $ruleData) {
            $rule = $this->makeRule($ruleData['code']);
            $covered = false;
            foreach ($evaluators as $evaluator) {
                if ($evaluator->supports($rule)) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $missing[] = $ruleData['code'];
            }
        }

        self::assertSame([], $missing, 'Règle(s) sans evaluator : ' . implode(', ', $missing));
    }

    private function makeRule(string $code): ScoringRule
    {
        return new ScoringRule($code, $code, '', '1.0.0', 0, 'test', 'https://example.test');
    }
}
