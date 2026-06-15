<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence d'édulcorants (aspartame, sucralose, stévia, etc.).
 *
 * Source : ANSES Avis 0-3 ans (2019)
 */
final class SweetenersEvaluator implements RuleEvaluator
{
    private const SWEETENER_E_CODES = [
        'e950',
        'e951',
        'e952',
        'e954',
        'e955',
        'e957',
        'e959',
        'e960',
        'e961',
        'e962',
        'e964',
        'e965',
        'e966',
        'e967',
        'e968',
        'e969',
    ];

    private const SWEETENER_KEYWORDS = [
        'aspartame',
        'sucralose',
        'stévia',
        'stevia',
        'acésulfame',
        'saccharine',
        'cyclamate',
        'maltitol',
        'xylitol',
        'sorbitol',
        'erythritol',
        'érythritol',
        'édulcorant',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'sweeteners' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $ingredients = $product->getIngredientsRaw();
        $additives = $product->getAdditives();

        // Pas de données du tout : on ne peut pas juger.
        if ((null === $ingredients || '' === trim($ingredients)) && [] === $additives) {
            return null;
        }

        $found = [];

        $additivesLower = array_map(
            static fn (string $a): string => mb_strtolower($a),
            $additives,
        );
        foreach (self::SWEETENER_E_CODES as $code) {
            foreach ($additivesLower as $additive) {
                if (str_contains($additive, $code)) {
                    $found[] = $code;
                    break;
                }
            }
        }

        if (null !== $ingredients && '' !== trim($ingredients)) {
            $ingredientsLower = mb_strtolower($ingredients);
            foreach (self::SWEETENER_KEYWORDS as $keyword) {
                if (str_contains($ingredientsLower, $keyword)) {
                    $found[] = $keyword;
                }
            }
        }

        if ([] === $found) {
            return new AppliedRuleDto(
                ruleCode: $rule->getCode(),
                ruleLabel: 'Sans édulcorant',
                pointsImpact: 0,
                reason: 'Aucun édulcorant détecté.',
                sourceName: $rule->getSourceName(),
                sourceUrl: $rule->getSourceUrl(),
                status: RuleStatus::Satisfied,
            );
        }

        $reason = \sprintf(
            'Édulcorant(s) détecté(s) : %s. L\'ANSES recommande de proscrire ces ingrédients chez les jeunes enfants.',
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
