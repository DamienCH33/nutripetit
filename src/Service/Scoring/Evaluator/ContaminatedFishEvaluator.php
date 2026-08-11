<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Poissons à risque mercure — DEUX niveaux ANSES (2019) :
 * 1. À ÉVITER < 3 ans (alerte critique, code contaminated_fish).
 * 2. À LIMITER 60 g/sem < 30 mois (malus réduit, code contaminated_fish_limit).
 */
final class ContaminatedFishEvaluator implements RuleEvaluator
{
    private const FISH_TO_AVOID = [
        'espadon',
        'marlin',
        'siki',
        'requin',
        'lamproie',
    ];

    private const FISH_TO_LIMIT = [
        'lotte',
        'baudroie',
        'loup',
        'bar',
        'bonite',
        'anguille',
        'empereur',
        'grenadier',
        'flétan',
        'fletan',
        'brochet',
        'dorade',
        'daurade',
        'raie',
        'sabre',
        'thon',
        'barbeau',
        'brème',
        'breme',
        'carpe',
        'silure',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'contaminated_fish' === $rule->getCode();
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

        $avoided = $this->match($ingredients, self::FISH_TO_AVOID);
        if ([] !== $avoided) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Poisson à éviter avant 3 ans',
                pointsImpact: $rule->getPointsImpact(),
                reason: \sprintf(
                    'Contient : %s. L\'ANSES recommande d\'ÉVITER ces espèces avant 3 ans (méthylmercure).',
                    implode(', ', $avoided),
                ),
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Triggered,
            );
        }

        $limited = $this->match($ingredients, self::FISH_TO_LIMIT);
        if ([] !== $limited) {
            return new AppliedRuleDto(
                ruleCode: 'contaminated_fish_limit',
                ruleLabel: 'Poisson à limiter (60 g/semaine avant 30 mois)',
                pointsImpact: intdiv($rule->getPointsImpact(), 2),
                reason: \sprintf(
                    'Contient : %s. L\'ANSES recommande de limiter ces espèces à 60 g/semaine avant 30 mois.',
                    implode(', ', $limited),
                ),
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Triggered,
            );
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: 'Sans poisson à risque',
            pointsImpact: 0,
            reason: 'Aucun poisson à risque (contaminants/mercure) détecté dans la liste d\'ingrédients.',
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Satisfied,
        );
    }

    /**
     * @param list<string> $list
     *
     * @return list<string>
     */
    private function match(string $ingredients, array $list): array
    {
        $pattern = '/\b(' . implode('|', array_map(
            static fn (string $w): string => preg_quote($w, '/'),
            $list,
        )) . ')\b/iu';

        preg_match_all($pattern, $ingredients, $matches);

        return array_values(array_unique($matches[1]));
    }
}
