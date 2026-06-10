<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte les arômes artificiels dans les produits alimentaires.
 * source PNNS 4 / Santé publique France, raison = arômes de synthèse déconseillés chez les jeunes enfants.
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
        if ([] === $found) {
            return null;
        }

        $reason = \sprintf(
            'Arôme(s) artificiel(s) détecté(s) : %s. Déconseillés chez le jeune enfant (PNNS 4).',
            implode(', ', \array_slice($found, 0, 3)),
        );

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
