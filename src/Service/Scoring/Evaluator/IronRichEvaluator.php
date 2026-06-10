<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Bonus : produit riche en fer (6-36 mois).
 * Source : ANSES Référentiels nutritionnels 0-3 ans (2019).
 */
final class IronRichEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'iron_rich' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $nutriments = $product->getNutriments();
        $iron = $nutriments['iron_100g'] ?? null;

        $threshold = $rule->getThresholdValue() ?? 0.0012;

        if (!is_numeric($iron) || (float) $iron < $threshold) {
            return null;
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: \sprintf(
                'Riche en fer (%.1f mg/100g), essentiel au développement cognitif du nourrisson (ANSES).',
                (float) $iron * 1000,
            ),
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
        );
    }
}
