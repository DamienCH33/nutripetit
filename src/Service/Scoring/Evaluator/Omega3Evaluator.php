<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Bonus : produit riche en oméga-3 (6-36 mois).
 * source ANSES AGPI-LC (2019).
 */
final class Omega3Evaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'omega3_rich' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $nutriments = $product->getNutriments();
        $omega3 = $nutriments['omega-3_100g'] ?? null;

        $threshold = $rule->getThresholdValue() ?? 0.0012;

        if (!is_numeric($omega3) || (float) $omega3 < $threshold) {
            return null;
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: \sprintf(
                'Riche en oméga-3 (%.1f mg/100g, DHA/EPA), essentiels au développement cérébral et visuel (ANSES).',
                (float) $omega3 * 1000,
            ),
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
        );
    }
}
