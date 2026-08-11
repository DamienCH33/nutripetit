<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Arômes en alimentation infantile — deux niveaux :
 * 1. Artificiels/synthèse → alerte critique (code artificial_flavors).
 * 2. Non qualifiés ("arômes", "arôme naturel de X") → malus réduit
 *    (code unqualified_flavors, pas d'alerte). Éducation au goût (ANSES/NPPM).
 */
final class ArtificialFlavorsEvaluator implements RuleEvaluator
{
    private const ARTIFICIAL_FLAVOR_KEYWORDS = [
        'arôme artificiel',
        'arome artificiel',
        'arôme de synthèse',
        'arome de synthese',
        'arôme naturel identique à l\'artificiel',
        'arome naturel identique a l\'artificiel',
        'arôme synthétique',
        'arômes synthétiques',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'artificial_flavors' === $rule->getCode();
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
            self::ARTIFICIAL_FLAVOR_KEYWORDS,
        )) . ')\b/iu';

        preg_match_all($pattern, $ingredients, $matches);
        $found = array_unique($matches[1]);

        if ([] !== $found) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: $rule->getLabel(),
                pointsImpact: $rule->getPointsImpact(),
                reason: \sprintf(
                    'Arôme(s) artificiel(s) détecté(s) : %s. Interdits/très encadrés en alimentation infantile.',
                    implode(', ', \array_slice($found, 0, 3)),
                ),
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Triggered,
            );
        }

        if (1 === preg_match('/\bar[oô]mes?\b/iu', $ingredients)) {
            return new AppliedRuleDto(
                ruleCode: 'unqualified_flavors',
                ruleLabel: 'Arômes ajoutés',
                pointsImpact: intdiv($rule->getPointsImpact(), 2),
                reason: 'Présence d\'arômes ajoutés (non précisés). L\'ajout d\'arômes est déconseillé dans l\'alimentation des tout-petits (éducation au goût).',
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Triggered,
            );
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: 'Sans arôme artificiel',
            pointsImpact: 0,
            reason: 'Aucun arôme artificiel détecté dans la liste d\'ingrédients.',
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Satisfied,
        );
    }
}
