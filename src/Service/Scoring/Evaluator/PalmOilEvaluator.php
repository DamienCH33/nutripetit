<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence d'huile de palme. Source : PNNS 4 + ANSES.
 */
final class PalmOilEvaluator implements RuleEvaluator
{
    private const PALM_OIL_KEYWORDS = [
        'huile de palme',
        'huile de palmiste',
        'graisse de palme',
        'palm oil',
        'palmiste',
        'matière grasse végétale (palme',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'palm_oil' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $ingredients = $product->getIngredientsRaw();

        if (null === $ingredients || '' === trim($ingredients)) {
            return null;
        }

        $ingredientsLower = mb_strtolower($ingredients);

        foreach (self::PALM_OIL_KEYWORDS as $keyword) {
            if (str_contains($ingredientsLower, $keyword)) {
                return new AppliedRuleDto(
                    ruleCode: $rule->getCode(),
                    ruleLabel: $rule->getLabel(),
                    pointsImpact: $rule->getPointsImpact(),
                    reason: 'Présence d\'huile de palme détectée dans la liste d\'ingrédients.',
                    sourceName: $rule->getSourceName(),
                    sourceUrl: $rule->getSourceUrl(),
                    status: RuleStatus::Triggered,
                );
            }
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: 'Sans huile de palme',
            pointsImpact: 0,
            reason: 'Aucune huile de palme détectée dans la liste d\'ingrédients.',
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Satisfied,
        );
    }
}
