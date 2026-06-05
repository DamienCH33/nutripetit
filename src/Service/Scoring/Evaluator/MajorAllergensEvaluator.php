<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence d'allergènes majeurs (information sans impact score).
 *
 * Source : Règlement EU 1169/2011 (INCO)
 * 14 allergènes à déclaration obligatoire selon le règlement INCO.
 *
 * L'impact est 0 : ce n'est pas un malus, juste une information importante
 * que les parents doivent voir mise en avant.
 */
final class MajorAllergensEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'major_allergens' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $allergens = $product->getAllergens();

        if ([] === $allergens) {
            return null;
        }

        // Nettoyage des préfixes OFF
        $cleanAllergens = array_map(
            static function (string $a): string {
                $parts = explode(':', $a);

                return ucfirst(end($parts));
            },
            $allergens,
        );

        $reason = \sprintf(
            'Allergène(s) majeur(s) déclaré(s) : %s. Vigilance recommandée selon le règlement INCO.',
            implode(', ', \array_slice($cleanAllergens, 0, 5)),
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
