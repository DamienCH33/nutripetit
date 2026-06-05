<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte si le produit est conforme à la Directive 2006/125/CE
 * (aliments pour bébés réglementés, composition adaptée nourrissons).
 *
 * Source : Directive 2006/125/CE
 */
final class BabyFoodCertifiedEvaluator implements RuleEvaluator
{
    private const BABY_FOOD_KEYWORDS = [
        'aliment pour bébé',
        'aliment pour nourrisson',
        'préparation pour nourrisson',
        'préparation de suite',
        'aliment infantile',
        'baby-food',
        'baby food',
        'pour bébé',
        'dès 4 mois',
        'dès 6 mois',
        'dès 8 mois',
        'dès 12 mois',
        'a partir de 4 mois',
        'a partir de 6 mois',
        'à partir de 4 mois',
        'à partir de 6 mois',
        'lait infantile',
        'lait de croissance',
    ];

    private const BABY_FOOD_CATEGORIES = [
        'baby-foods',
        'baby-food',
        'baby-milks',
        'infant-formula',
        'infant-and-toddler-foods',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'baby_food_certified' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        // Vérification dans les catégories OFF
        $offData = $product->getOffRawData();
        $categoriesTags = $offData['categories_tags'] ?? [];

        if (\is_array($categoriesTags)) {
            $categoriesLower = array_map(
                static fn ($c): string => \is_string($c) ? mb_strtolower($c) : '',
                $categoriesTags,
            );

            foreach (self::BABY_FOOD_CATEGORIES as $babyCategory) {
                foreach ($categoriesLower as $category) {
                    if (str_contains($category, $babyCategory)) {
                        return $this->createAppliedRule(
                            $rule,
                            "Catégorie OpenFoodFacts : {$category}",
                        );
                    }
                }
            }
        }

        // Fallback
        $nameLower = mb_strtolower($product->getName());
        foreach (self::BABY_FOOD_KEYWORDS as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return $this->createAppliedRule(
                    $rule,
                    "Mention adaptée aux nourrissons détectée : « {$keyword} »",
                );
            }
        }

        return null;
    }

    private function createAppliedRule(ScoringRule $rule, string $reason): AppliedRuleDto
    {
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
