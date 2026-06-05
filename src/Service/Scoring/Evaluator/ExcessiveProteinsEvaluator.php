<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte un excès de protéines (>15% AET) chez le jeune enfant.
 *
 * Source : ANSES Référentiels nutritionnels 0-3 ans (2019)
 * Les protéines excessives favorisent le risque de surpoids ultérieur.
 */
final class ExcessiveProteinsEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'excessive_proteins' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $nutriments = $product->getNutriments();
        $proteinsPer100g = $nutriments['proteins_100g'] ?? null;
        $energyKcalPer100g = $nutriments['energy-kcal_100g'] ?? null;

        if (
            !is_numeric($proteinsPer100g)
            || !is_numeric($energyKcalPer100g)
            || (float) $energyKcalPer100g <= 0.0
        ) {
            return null;
        }

        $proteinsValue = (float) $proteinsPer100g;
        $energyValue = (float) $energyKcalPer100g;

        // Calcul du pourcentage de protéines en % de l'AET
        $proteinsPercentageOfAET = ($proteinsValue * 4) / $energyValue * 100;

        $threshold = $rule->getThresholdValue() ?? 15.0;

        if ($proteinsPercentageOfAET <= $threshold) {
            return null;
        }

        $reason = \sprintf(
            '%.1f%% de protéines (calculé : %.1fg/100g × 4 / %.0f kcal/100g). Seuil ANSES : %.0f%%.',
            $proteinsPercentageOfAET,
            $proteinsValue,
            $energyValue,
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
