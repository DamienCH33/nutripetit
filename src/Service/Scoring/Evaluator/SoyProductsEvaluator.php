<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte les aliments à base de soja. Source : ANSES / Afssa (2005).
 */
final class SoyProductsEvaluator implements RuleEvaluator
{
    private const SOY_FOOD_KEYWORDS = [
        'protéines de soja',
        'protéine de soja',
        'isolat de soja',
        'concentré de soja',
        'farine de soja',
        'graines de soja',
        'flocons de soja',
        'lait de soja',
        'boisson au soja',
        'boisson de soja',
        'jus de soja',
        'yaourt au soja',
        'yaourt de soja',
        'dessert au soja',
        'tofu',
        'tempeh',
        'tempé',
        'edamame',
        'tonyu',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'soy_products' === $rule->getCode();
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

        $pattern = '/\b(' . implode('|', array_map(
            static fn(string $w): string => preg_quote($w, '/'),
            self::SOY_FOOD_KEYWORDS,
        )) . ')\b/iu';

        preg_match_all($pattern, $ingredients, $matches);
        $found = array_unique($matches[1]);

        if ([] === $found) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Sans soja',
                pointsImpact: 0,
                reason: 'Aucun aliment à base de soja détecté dans la liste d\'ingrédients.',
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
                'Présence de soja détectée : %s. Consommation régulière déconseillée avant 3 ans (ANSES).',
                implode(', ', \array_slice($found, 0, 3)),
            ),
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Triggered,
        );
    }
}
