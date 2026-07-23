<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte une teneur en sucres totaux trop élevée par rapport à l'énergie.
 *
 * Source : WHO Europe — Nutrient and Promotion Profile Model (NPPM), 2022.
 * Réf. WHO-EURO-2022-6681-46447-67287.
 * Le NPPM fixe un maximum de 15% de l'énergie issue des sucres totaux pour les
 * repas et snacks 6-36 mois. Calcul du % énergie sucre donné par le NPPM (p.18) :
 * (sucres g / kcal) × 400, mathématiquement identique à (sucres × 4 / kcal) × 100.
 *
 * Note : le champ "sugars_100g" d'Open Food Facts est une approximation de la
 * définition NPPM du sucre total (qui inclut sucres libérés par mixage + lactose).
 */
final class TotalSugarsEnergyEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'total_sugars_energy' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $nutriments = $product->getNutriments();
        $sugarsPer100g = $nutriments['sugars_100g'] ?? null;
        $energyKcalPer100g = $nutriments['energy-kcal_100g'] ?? null;

        // Données absentes ou énergie nulle -> non évaluable (jamais un "satisfait" par défaut).
        if (
            !is_numeric($sugarsPer100g)
            || !is_numeric($energyKcalPer100g)
            || (float) $energyKcalPer100g <= 0.0
        ) {
            return null;
        }

        $sugarsValue = (float) $sugarsPer100g;
        $energyValue = (float) $energyKcalPer100g;

        // Même méthode que la règle protéines : macronutriment × 4 kcal/g, en % de l'énergie.
        $sugarsPercentageOfAET = ($sugarsValue * 4) / $energyValue * 100;
        $threshold = $rule->getThresholdValue() ?? 15.0;

        if ($sugarsPercentageOfAET <= $threshold) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Teneur en sucres adaptée',
                pointsImpact: 0,
                reason: \sprintf('%.1f%% de sucres (énergie), sous le repère OMS de %.0f%%.', $sugarsPercentageOfAET, $threshold),
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Satisfied,
            );
        }

        $reason = \sprintf(
            '%.1f%% de l\'énergie vient des sucres (calculé : %.1fg/100g × 4 / %.0f kcal/100g). Repère OMS : %.0f%%.',
            $sugarsPercentageOfAET,
            $sugarsValue,
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
            status: RuleStatus::Triggered,
        );
    }
}
