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
 * Le NPPM (tableau 1) distingue les catégories :
 * - plats salés et snacks non fruités : sucres totaux ≤ 15 % de l'énergie ;
 * - céréales sèches, purées et desserts de fruits, fruits secs : alerte au-delà de 30 % ;
 * - produits laitiers : alerte au-delà de 40 %.
 * Appliquer 15 % à une compote la pénaliserait pour le sucre naturel des fruits.
 * Calcul du % énergie sucre donné par le NPPM (p.18) :
 * (sucres g / kcal) × 400, mathématiquement identique à (sucres × 4 / kcal) × 100.
 *
 * Note : le champ "sugars_100g" d'Open Food Facts est une approximation de la
 * définition NPPM du sucre total (qui inclut sucres libérés par mixage + lactose).
 */
final class TotalSugarsEnergyEvaluator implements RuleEvaluator
{
    private const LIMIT_SAVOURY = 15.0;
    private const LIMIT_FRUIT_OR_CEREAL = 30.0;
    private const LIMIT_DAIRY = 40.0;

    private const DAIRY_MARKERS = ['yogurt', 'yaourt', 'dairies', 'dairy', 'laitier', 'fromage', 'cheese', 'petit-suisse', 'creme-dessert', 'crème dessert'];
    private const FRUIT_OR_CEREAL_MARKERS = ['compote', 'fruit', 'pomme', 'poire', 'banane', 'mangue', 'abricot', 'applesauce', 'puree-de-fruits', 'dessert', 'cereal', 'céréale', 'cereale', 'porridge', 'bouillie', 'smoothie', 'dried-fruit'];

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
        $threshold = $this->thresholdFor($product);

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
            '%.1f%% de l\'énergie vient des sucres (calculé : %.1fg/100g × 4 / %.0f kcal/100g). Repère OMS Europe pour ce type de produit : %.0f%%.',
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

    /**
     * Seuil NPPM selon la catégorie. Laitages d'abord (un « yaourt aux fruits »
     * reste un laitage), puis fruits/céréales, sinon plat salé ou snack (15 %).
     */
    private function thresholdFor(Product $product): float
    {
        $haystack = mb_strtolower($product->getName() . ' ' . implode(' ', array_filter(
            (array) ($product->getOffRawData()['categories_tags'] ?? []),
            'is_string',
        )));

        foreach (self::DAIRY_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return self::LIMIT_DAIRY;
            }
        }
        foreach (self::FRUIT_OR_CEREAL_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return self::LIMIT_FRUIT_OR_CEREAL;
            }
        }

        return self::LIMIT_SAVOURY;
    }
}
