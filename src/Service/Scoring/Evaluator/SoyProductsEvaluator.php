<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte les aliments à base de soja (protéines, tofu, boissons, yaourts…).
 *
 * Source : ANSES / Afssa, « Sécurité et bénéfices des phyto-estrogènes » (2005).
 * Le soja est riche en isoflavones (phytoestrogènes) ; sa consommation régulière
 * est déconseillée chez l'enfant de moins de 3 ans.
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
            static fn (string $w): string => preg_quote($w, '/'),
            self::SOY_FOOD_KEYWORDS,
        )) . ')\b/iu';

        preg_match_all($pattern, $ingredients, $matches);
        $found = array_unique($matches[1]);
        if ([] === $found) {
            return null;
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
        );
    }
}
