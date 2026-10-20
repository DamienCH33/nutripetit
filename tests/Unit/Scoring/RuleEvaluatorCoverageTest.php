<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\DataFixtures\ScoringRuleFixtures;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;
use App\Service\Scoring\ScoreCalculator;
use ReflectionProperty;
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
        $property = new ReflectionProperty(ScoreCalculator::class, 'evaluators');
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

    /**
     * Garde-fou inverse : un evaluator sans règle semée ne s'exécute jamais.
     * C'est arrivé avec honey_botulism et total_sugars_energy (évaluateurs codés
     * et testés, mais absents du provider, donc jamais en base).
     */
    public function testEveryEvaluatorHasASeededRule(): void
    {
        self::bootKernel();

        $calculator = self::getContainer()->get(ScoreCalculator::class);
        $property = new ReflectionProperty(ScoreCalculator::class, 'evaluators');
        /** @var list<RuleEvaluator> $evaluators */
        $evaluators = iterator_to_array($property->getValue($calculator), false);

        $rules = array_map(
            fn (array $data): ScoringRule => $this->makeRule($data['code']),
            ScoringRuleFixtures::getRules(),
        );

        $orphans = [];
        foreach ($evaluators as $evaluator) {
            $used = false;
            foreach ($rules as $rule) {
                if ($evaluator->supports($rule)) {
                    $used = true;
                    break;
                }
            }
            if (!$used) {
                $orphans[] = $evaluator::class;
            }
        }

        self::assertSame([], $orphans, 'Evaluator(s) sans règle semée : ' . implode(', ', $orphans));
    }

    private function makeRule(string $code): ScoringRule
    {
        return new ScoringRule($code, $code, '', '1.1.0', 0, 'test', 'https://example.test');
    }
}
