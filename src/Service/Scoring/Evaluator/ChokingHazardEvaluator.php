<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte les aliments à risque d'étouffement (entiers) avant 3 ans.
 * Source : ANSES Avis 0-3 ans (2019).
 */
final class ChokingHazardEvaluator implements RuleEvaluator
{
    private const HAZARD_KEYWORDS = [
        'fruits à coque entiers',
        'noisette entière',
        'noisettes entières',
        'amande entière',
        'amandes entières',
        'noix entière',
        'noix entières',
        'cacahuète entière',
        'cacahuètes entières',
        'arachide entière',
        'raisin entier',
        'raisins entiers',
        'raisins secs',
        'fruits secs',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'choking_hazard' === $rule->getCode();
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
            self::HAZARD_KEYWORDS,
        )) . ')\b/iu';

        preg_match_all($pattern, $ingredients, $matches);
        $found = array_unique($matches[1]);

        if ([] === $found) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Sans aliment à risque d\'étouffement',
                pointsImpact: 0,
                reason: 'Aucun aliment à risque d\'étouffement détecté dans la liste d\'ingrédients.',
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Satisfied,
            );
        }

        $reason = \sprintf(
            'Aliment(s) à risque d\'étouffement détecté(s) : %s. À ne pas proposer entiers avant 3 ans (ANSES).',
            implode(', ', array_unique(\array_slice($found, 0, 3))),
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
