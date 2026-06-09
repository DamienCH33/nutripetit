<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Dto\AppliedRuleDto;
use App\Dto\ScoreCalculationResultDto;
use App\Entity\Product;
use App\Repository\ScoringRuleRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Calcule le score nutritionnel NutriPetit d'un produit.
 */
final readonly class ScoreCalculator
{
    public const ALGO_VERSION = '1.0.0';
    private const SCORE_BASE = 100;

    /**
     * @param iterable<RuleEvaluator> $evaluators
     */
    public function __construct(
        private ScoringRuleRepository $ruleRepository,
        #[AutowireIterator('app.rule_evaluator')]
        private iterable $evaluators,
    ) {
    }

    public function calculate(Product $product, ?int $babyAgeMonths = null): ScoreCalculationResultDto
    {
        $rules = $this->ruleRepository->findActiveByVersion(self::ALGO_VERSION);
        $appliedRules = [];

        foreach ($rules as $rule) {
            if (!$rule->appliesToAge($babyAgeMonths)) {
                continue;
            }

            $applied = $this->evaluateRule($product, $rule, $babyAgeMonths);
            if (null !== $applied) {
                $appliedRules[] = $applied;
            }
        }

        $totalImpact = array_sum(
            array_map(static fn (AppliedRuleDto $r): int => $r->pointsImpact, $appliedRules),
        );

        $finalScore = max(0, min(100, self::SCORE_BASE + $totalImpact));
        $level = $this->determineLevel($finalScore);

        return new ScoreCalculationResultDto(
            finalScore: $finalScore,
            level: $level,
            appliedRules: $appliedRules,
            algoVersion: self::ALGO_VERSION,
            babyAgeMonths: $babyAgeMonths,
        );
    }

    /**
     * Délègue l'évaluation d'une règle à son évaluateur dédié.
     */
    private function evaluateRule(
        Product $product,
        \App\Entity\ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($rule)) {
                return $evaluator->evaluate($product, $rule, $babyAgeMonths);
            }
        }

        return null;
    }

    /**
     * Détermine le niveau du score selon l'échelle 5 niveaux NutriPetit.
     */
    private function determineLevel(int $score): string
    {
        return match (true) {
            $score >= 95 => 'ideal',
            $score >= 85 => 'good',
            $score >= 75 => 'occasional',
            $score >= 70 => 'limit',
            default => 'discouraged',
        };
    }
}
