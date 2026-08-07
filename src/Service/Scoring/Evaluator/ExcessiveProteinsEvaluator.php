<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Évalue la part de viande/poisson/œuf (VPO) d'un plat pour bébé.
 *
 * Référentiel : SFP / ANSES (Avis 0-3 ans, 2019) — la quantité de VPO doit être
 * modérée (repères : 10 g/j à 6-12 mois, 20 g/j à 1-2 ans, 30 g/j à 2-3 ans).
 *
 * Ces repères sont EN GRAMMES PAR JOUR. Les convertir au niveau d'un produit
 * exigerait la portion réellement consommée ET l'âge de l'enfant — deux données
 * qu'Open Food Facts ne fournit pas de façon fiable (product_quantity = taille du
 * conditionnement, pas la portion ; âge minimum le plus souvent absent). On évalue
 * donc la PART de VPO dans la recette (% des ingrédients VPO), indépendante de la
 * portion et de l'âge. La norme des plats bébé conformes du marché est ~8-12 % ;
 * au-delà d'un plafond (15 %), la part de VPO est jugée excessive.
 *
 * Fallback : si aucun ingrédient VPO n'a de % exploitable, on retombe sur un
 * contrôle du % de protéines rapporté à l'AET, avec un seuil adapté au type de
 * produit (un plat salé est légitimement plus protéiné qu'un dessert de fruits).
 */
final class ExcessiveProteinsEvaluator implements RuleEvaluator
{
    /**
     * Ingrédients VPO — id canoniques OFF exacts (pas de sous-chaîne :
     * "en:eggplant" contient "egg", "en:meat-substitute" contient "meat").
     */
    private const VPO_INGREDIENT_IDS = [
        'en:meat',
        'en:beef',
        'en:beef-meat',
        'en:veal',
        'en:veal-meat',
        'en:pork',
        'en:pork-meat',
        'en:chicken',
        'en:chicken-meat',
        'en:turkey',
        'en:turkey-meat',
        'en:ham',
        'en:lamb',
        'en:lamb-meat',
        'en:duck',
        'en:rabbit',
        'en:rabbit-meat',
        'en:guinea-fowl',
        'en:fish',
        'en:cod',
        'en:salmon',
        'en:hake',
        'en:pollock',
        'en:sardine',
        'en:tuna',
        'en:whiting',
        'en:sole',
        'en:trout',
        'en:sea-bream',
        'en:plaice',
        'en:coley',
        'en:mackerel',
        'en:egg',
        'en:eggs',
        'en:egg-yolk',
        'en:whole-egg',
    ];

    /**
     * Plafond de part de VPO (% de la recette) au-delà duquel elle est jugée
     * excessive. Norme marché conforme ~8-12 % ; 15 % laisse une marge et
     * n'attrape que les recettes manifestement sur-chargées en viande/poisson.
     * (Plafond pragmatique dérivé de la norme du marché, pas une valeur ANSES
     * directe — le référentiel s'exprime en g/jour, non convertible ici faute
     * de portion et d'âge fiables.).
     */
    private const VPO_PERCENT_LIMIT = 15.0;

    /** Seuils de repli en % de l'AET, par type de produit. */
    private const FALLBACK_AET_MAIN_MEAL = 30.0;
    private const FALLBACK_AET_DESSERT = 15.0;
    private const FALLBACK_AET_DEFAULT = 15.0;

    public function supports(ScoringRule $rule): bool
    {
        return 'excessive_proteins' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        // --- Méthode principale : part de VPO dans la recette ---
        $vpoPercent = $this->computeVpoPercent($product);

        if (null !== $vpoPercent) {
            if ($vpoPercent <= self::VPO_PERCENT_LIMIT) {
                return new AppliedRuleDto(
                    ruleCode: $rule->getCode(),
                    ruleLabel: 'Part de viande/poisson adaptée',
                    pointsImpact: 0,
                    reason: \sprintf(
                        '%.0f%% de viande/poisson dans la recette, part modérée conforme aux repères SFP.',
                        $vpoPercent,
                    ),
                    sourceName: $rule->getSourceName(),
                    sourceUrl: $rule->getSourceUrl(),
                    status: RuleStatus::Satisfied,
                );
            }

            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: $rule->getLabel(),
                pointsImpact: $rule->getPointsImpact(),
                reason: \sprintf(
                    '%.0f%% de viande/poisson dans la recette, au-dessus de la part modérée recommandée.',
                    $vpoPercent,
                ),
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Triggered,
            );
        }

        // --- Fallback : % de protéines / AET, seuil par type ---
        return $this->evaluateByAetFallback($product, $rule);
    }

    /**
     * Somme le % des ingrédients VPO. Renvoie null si aucun ingrédient VPO
     * n'a de pourcentage exploitable (→ bascule sur le fallback AET).
     */
    private function computeVpoPercent(Product $product): ?float
    {
        $ingredients = $product->getOffRawData()['ingredients'] ?? [];
        if (!\is_array($ingredients)) {
            return null;
        }

        $total = null;
        foreach ($ingredients as $ingredient) {
            if (!\is_array($ingredient)) {
                continue;
            }
            $id = $ingredient['id'] ?? null;
            if (!\is_string($id) || !\in_array($id, self::VPO_INGREDIENT_IDS, true)) {
                continue;
            }
            $percent = $ingredient['percent'] ?? $ingredient['percent_estimate'] ?? null;
            if (is_numeric($percent)) {
                $total = ($total ?? 0.0) + (float) $percent;
            }
        }

        return $total;
    }

    /**
     * Fallback : % protéines / AET, avec un seuil adapté au type de produit.
     */
    private function evaluateByAetFallback(Product $product, ScoringRule $rule): ?AppliedRuleDto
    {
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
        $proteinsPercentageOfAET = $proteinsValue * 4 / $energyValue * 100;
        $threshold = $this->resolveAetThreshold($product);

        if ($proteinsPercentageOfAET <= $threshold) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Apport en protéines adapté',
                pointsImpact: 0,
                reason: \sprintf('%.1f%% de protéines (AET), sous le seuil de %.0f%%.', $proteinsPercentageOfAET, $threshold),
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Satisfied,
            );
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: \sprintf(
                '%.1f%% de protéines (%.1f g/100g × 4 / %.0f kcal/100g). Seuil %.0f%%.',
                $proteinsPercentageOfAET,
                $proteinsValue,
                $energyValue,
                $threshold,
            ),
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Triggered,
        );
    }

    private function resolveAetThreshold(Product $product): float
    {
        $categories = $product->getOffRawData()['categories_tags'] ?? [];
        if (!\is_array($categories)) {
            return self::FALLBACK_AET_DEFAULT;
        }

        foreach (['en:main-meals-for-babies', 'en:meals', 'en:seafood', 'en:fishes'] as $tag) {
            if (\in_array($tag, $categories, true)) {
                return self::FALLBACK_AET_MAIN_MEAL;
            }
        }

        foreach (['en:desserts', 'en:compotes'] as $tag) {
            if (\in_array($tag, $categories, true)) {
                return self::FALLBACK_AET_DESSERT;
            }
        }

        return self::FALLBACK_AET_DEFAULT;
    }
}
