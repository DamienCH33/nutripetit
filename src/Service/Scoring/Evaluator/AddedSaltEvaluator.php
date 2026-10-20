<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Sodium rapporté à l'énergie. Référentiel OMS Europe NPPM 2022 (6-36 mois),
 * tableau 1 : sodium ≤ 50 mg/100 kcal pour la plupart des produits, ≤ 100 mg/100 kcal
 * pour les produits laitiers et les plats au fromage. Conversion sel (g) × 400 = sodium (mg).
 * Énergie absente : plafond légal de la directive 2006/125/CE, annexe II, 4.1
 * (200 mg de sodium/100 g, soit 0,5 g de sel/100 g).
 * L'ANSES (avis 2019, 0-3 ans) recommande de ne pas ajouter de sel, sans seuil chiffré.
 */
final class AddedSaltEvaluator implements RuleEvaluator
{
    private const SODIUM_MG_PER_100KCAL_LIMIT = 50.0;
    private const SODIUM_MG_PER_100KCAL_LIMIT_DAIRY = 100.0;
    private const FALLBACK_SALT_G_PER_100G_LIMIT = 0.5;

    /** Marqueurs (catégories OFF ou nom) des produits laitiers et plats au fromage. */
    private const DAIRY_MARKERS = ['yogurt', 'yaourt', 'dairies', 'dairy', 'laitier', 'fromage', 'cheese', 'fromage-blanc', 'petit-suisse'];

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
            $limit = $this->isDairy($product) ? self::SODIUM_MG_PER_100KCAL_LIMIT_DAIRY : self::SODIUM_MG_PER_100KCAL_LIMIT;

            if ($sodiumPer100kcal <= $limit) {
                return $this->satisfied($rule, \sprintf(
                    '%.0f mg de sodium/100 kcal, sous le plafond OMS Europe (NPPM 2022) de %.0f mg/100 kcal.',
                    $sodiumPer100kcal,
                    $limit,
                ));
            }

            return $this->triggered($rule, \sprintf(
                '%.0f mg de sodium/100 kcal (%.2f g de sel/100 g), au-dessus du plafond OMS Europe (NPPM 2022) de %.0f mg/100 kcal.',
                $sodiumPer100kcal,
                $saltValue,
                $limit,
            ));
        }

        $threshold = self::FALLBACK_SALT_G_PER_100G_LIMIT;

        if ($saltValue <= $threshold) {
            return $this->satisfied($rule, \sprintf(
                'Teneur en sel de %.2f g/100 g, sous le plafond légal des aliments pour bébés (%.1f g/100 g).',
                $saltValue,
                $threshold,
            ));
        }

        return $this->triggered($rule, \sprintf(
            'Teneur en sel de %.2f g/100 g, au-dessus du plafond légal des aliments pour bébés (%.1f g/100 g, directive 2006/125/CE).',
            $saltValue,
            $threshold,
        ));
    }

    private function isDairy(Product $product): bool
    {
        $haystack = mb_strtolower($product->getName() . ' ' . implode(' ', array_filter(
            (array) ($product->getOffRawData()['categories_tags'] ?? []),
            'is_string',
        )));

        foreach (self::DAIRY_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
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
