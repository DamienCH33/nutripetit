<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence de sucres ajoutés dans la liste d'ingrédients.
 *
 * Source : OMS Guideline: Sugars intake for adults and children (2015)
 * https://www.who.int/publications/i/item/9789241549028
 *
 * L'OMS recommande zéro sucre ajouté pour les nourrissons.
 */
final class AddedSugarsEvaluator implements RuleEvaluator
{
    private const ADDED_SUGAR_KEYWORDS = [
        'sucre',
        'saccharose',
        'sirop de glucose',
        'sirop de mais',
        'sirop de maïs',
        'sirop de fructose',
        'sirop d\'agave',
        'dextrose',
        'fructose',
        'maltose',
        'miel',
        'mélasse',
        'sirop d\'érable',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'added_sugars' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $ingredients = $product->getIngredientsRaw();

        // Pas de données -> on ne peut pas juger.
        if (null === $ingredients || '' === trim($ingredients)) {
            return null;
        }

        $ingredientsLower = mb_strtolower($ingredients);
        $foundKeywords = [];
        foreach (self::ADDED_SUGAR_KEYWORDS as $keyword) {
            if (str_contains($ingredientsLower, $keyword)) {
                $foundKeywords[] = $keyword;
            }
        }

        // Aucun sucre détecté : contrôle passé.
        if ([] === $foundKeywords) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Sans sucre ajouté',
                pointsImpact: 0,
                reason: 'Aucun sucre ajouté détecté dans la liste d\'ingrédients.',
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Satisfied,
            );
        }

        $reason = \sprintf(
            'Présence détectée dans la liste d\'ingrédients : %s',
            implode(', ', \array_slice($foundKeywords, 0, 3)),
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
