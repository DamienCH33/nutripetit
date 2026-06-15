<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte les additifs controversés (EFSA/ANSES).
 *
 * Source : Règlement EU 1333/2008 + Évaluations ANSES
 */
final class ControversialAdditivesEvaluator implements RuleEvaluator
{
    private const CONTROVERSIAL_E_CODES = [
        'e102',
        'e104',
        'e110',
        'e122',
        'e124',
        'e129',
        'e171',
        'e150c',
        'e150d',
        'e249',
        'e250',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'controversial_additives' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $additives = $product->getAdditives();

        if ([] === $additives) {
            return null;
        }

        $additivesLower = array_map(
            static fn(string $a): string => mb_strtolower($a),
            $additives,
        );

        $found = [];
        foreach (self::CONTROVERSIAL_E_CODES as $code) {
            foreach ($additivesLower as $additive) {
                if (str_contains($additive, $code)) {
                    $found[] = strtoupper($code);
                    break;
                }
            }
        }

        if ([] === $found) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Sans additif controversé',
                pointsImpact: 0,
                reason: 'Aucun additif controversé (colorant azoïque, E171, nitrites…) détecté.',
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Satisfied,
            );
        }

        $reason = \sprintf(
            'Additif(s) controversé(s) détecté(s) : %s. Évaluation préoccupante par l\'EFSA/ANSES.',
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
