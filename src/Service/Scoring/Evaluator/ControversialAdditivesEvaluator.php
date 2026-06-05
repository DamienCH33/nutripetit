<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence d'additifs controversés évalués comme préoccupants
 * par l'EFSA et l'ANSES, particulièrement pour les jeunes enfants.
 *
 * Source : Règlement EU 1333/2008 + Évaluations ANSES
 * Colorants azoïques + dioxyde de titane nanoparticulaire (E171 interdit en France
 * depuis 2020 dans l'alimentation).
 */
final class ControversialAdditivesEvaluator implements RuleEvaluator
{
    /**
     * Liste des additifs identifiés comme préoccupants.
     */
    private const CONTROVERSIAL_E_CODES = [
        'e102', // Tartrazine (colorant azoïque)
        'e104', // Jaune de quinoléine
        'e110', // Sunset Yellow FCF
        'e122', // Carmoisine
        'e124', // Ponceau 4R
        'e129', // Rouge allura AC
        'e171', // Dioxyde de titane (interdit alimentaire en France)
        'e150c', // Caramel ammoniacal
        'e150d', // Caramel sulfite-ammoniacal
        'e249',  // Nitrite de potassium
        'e250',  // Nitrite de sodium
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
        $additivesLower = array_map(
            static fn (string $a): string => mb_strtolower($a),
            $product->getAdditives(),
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
            return null;
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
        );
    }
}
