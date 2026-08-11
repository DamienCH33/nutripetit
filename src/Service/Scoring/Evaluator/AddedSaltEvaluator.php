<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Sodium rapporté à l'énergie. Référentiel OMS Europe NPPM 2022 (6-36 mois) :
 * sodium ≤ 50 mg/100 kcal. Conversion sel (g) × 400 = sodium (mg).
 * Fallback si énergie absente : seuil massique 0,3 g sel/100 g (ANSES/PNNS).
 */
final class AddedSaltEvaluator implements RuleEvaluator
{
    private const SODIUM_MG_PER_100KCAL_LIMIT = 50.0;
    private const FALLBACK_SALT_G_PER_100G_LIMIT = 0.3;

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
        $saltPer100g = $nutriments['salt_100g'] ?? null;

        if (null === $saltPer100g || !is_numeric($saltPer100g)) {
            return null;
        }

        $saltValue = (float) $saltPer100g;
        $sodiumMgPer100g = $saltValue * 400.0;
        $energyKcal = $nutriments['energy-kcal_100g'] ?? null;

        if (is_numeric($energyKcal) && (float) $energyKcal > 0.0) {
            $sodiumPer100kcal = $sodiumMgPer100g * 100.0 / (float) $energyKcal;

            if ($sodiumPer100kcal <= self::SODIUM_MG_PER_100KCAL_LIMIT) {
                return $this->satisfied($rule, \sprintf(
                    '%.0f mg de sodium/100 kcal, sous le plafond NPPM (OMS Europe 2022) de %.0f mg/100 kcal.',
                    $sodiumPer100kcal,
                    self::SODIUM_MG_PER_100KCAL_LIMIT,
                ));
            }

            return $this->triggered($rule, \sprintf(
                '%.0f mg de sodium/100 kcal (%.2f g de sel/100 g), au-dessus du plafond NPPM (OMS Europe 2022) de %.0f mg/100 kcal.',
                $sodiumPer100kcal,
                $saltValue,
                self::SODIUM_MG_PER_100KCAL_LIMIT,
            ));
        }

        $threshold = $rule->getThresholdValue() ?? self::FALLBACK_SALT_G_PER_100G_LIMIT;

        if ($saltValue <= $threshold) {
            return $this->satisfied($rule, \sprintf(
                'Teneur en sel de %.2f g/100 g, sous le seuil de %.2f g/100 g.',
                $saltValue,
                $threshold,
            ));
        }

        return $this->triggered($rule, \sprintf(
            'Teneur en sel de %.2f g/100 g, supérieure au seuil de %.2f g/100 g pour les nourrissons.',
            $saltValue,
            $threshold,
        ));
    }

    private function satisfied(ScoringRule $rule, string $reason): AppliedRuleDto
    {
        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: 'Teneur en sel maîtrisée',
            pointsImpact: 0,
            reason: $reason,
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Satisfied,
        );
    }

    private function triggered(ScoringRule $rule, string $reason): AppliedRuleDto
    {
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
