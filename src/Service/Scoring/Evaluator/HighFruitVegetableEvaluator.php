<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Bonus pour les produits riches en fruits/légumes (>50% par OFF).
 */
final class HighFruitVegetableEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'high_fruit_vegetable' === $rule->getCode();
    }

    public function evaluate(Product $product, ScoringRule $rule, ?int $babyAgeMonths): ?AppliedRuleDto
    {
        $nutriments = $product->getNutriments();
        $estimate = $nutriments['fruits-vegetables-legumes-estimate-from-ingredients_100g']
            ?? $nutriments['fruits-vegetables-nuts-estimate-from-ingredients_100g']
            ?? null;

        if (!is_numeric($estimate) || (float) $estimate < 50.0) {
            return null;
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: \sprintf('Composé à %.0f%% de fruits et légumes selon l\'analyse Open Food Facts.', (float) $estimate),
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
        );
    }
}
