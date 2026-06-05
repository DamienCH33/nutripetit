<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte un excès de sel dans le produit (>0.3g/100g pour les nourrissons).
 *
 * Source : ANSES Avis 0-3 ans (2019), Repères PNNS 4
 * Le sel ajouté est déconseillé avant 12 mois.
 */
final class AddedSaltEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'added_salt' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $nutriments = $product->getNutriments();

        // OpenFoodFacts utilise "salt_100g" en grammes pour 100g
        $saltPer100g = $nutriments['salt_100g'] ?? null;

        if (null === $saltPer100g || !is_numeric($saltPer100g)) {
            return null;
        }

        $threshold = $rule->getThresholdValue() ?? 0.3;
        $saltValue = (float) $saltPer100g;

        if ($saltValue <= $threshold) {
            return null;
        }

        $reason = \sprintf(
            'Teneur en sel de %.2fg/100g, supérieure au seuil ANSES de %.2fg/100g pour les nourrissons.',
            $saltValue,
            $threshold,
        );

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: $reason,
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
        );
    }
}
