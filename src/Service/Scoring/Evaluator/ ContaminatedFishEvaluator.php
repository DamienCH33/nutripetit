<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte les poisson(s) à risque (contaminants/mercure) détecté(s)
 * Source : ANSES Avis 0-3 ans (2019).
 */
final class  ContaminatedFishEvaluator implements RuleEvaluator
{

    private const RISKY_FISH = [
        // À éviter (grands prédateurs, mercure)
        'espadon',
        'marlin',
        'siki',
        'requin',
        'lamproie',
        // Prédateurs sauvages à limiter
        'lotte',
        'baudroie',
        'loup',
        'bar',
        'bonite',
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
        // Eau douce bio-accumulateurs (PCB)
        'anguille',
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

        $pattern = '/\b(' . implode('|', array_map(
            static fn(string $w): string => preg_quote($w, '/'),
            self::RISKY_FISH,
        )) . ')\b/iu';

        preg_match_all($pattern, $ingredients, $matches);
        $found = array_unique($matches[1]);
        if ([] === $found) {
            return null;
        }

        $reason = \sprintf(
            'Poisson(s) à risque (contaminants/mercure) détecté(s) : %s. À éviter chez le jeune enfant (ANSES).',
            implode(', ', array_unique(\array_slice($found, 0, 3))),
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
