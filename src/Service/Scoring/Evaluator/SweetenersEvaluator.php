<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence d'édulcorants (aspartame, sucralose, stévia, etc.).
 *
 * Source : ANSES Avis 0-3 ans (2019)
 * L'ANSES recommande de proscrire les édulcorants chez les jeunes enfants,
 * leur sécurité n'ayant pas été évaluée pour cette population.
 */
final class SweetenersEvaluator implements RuleEvaluator
{
    /**
     * Liste des codes E des édulcorants reconnus.
     */
    private const SWEETENER_E_CODES = [
        'e950', // Acésulfame K
        'e951', // Aspartame
        'e952', // Cyclamate
        'e954', // Saccharine
        'e955', // Sucralose
        'e957', // Thaumatine
        'e959', // Néohespéridine DC
        'e960', // Stévia / glycosides de stéviol
        'e961', // Néotame
        'e962', // Sel d\'aspartame-acésulfame
        'e964', // Sirop de polyglycitol
        'e965', // Maltitol
        'e966', // Lactitol
        'e967', // Xylitol
        'e968', // Erythritol
        'e969', // Advantame
    ];

    /**
     * Mots-clés textuels pour les édulcorants (en plus des codes E).
     */
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
        $found = [];

        // Vérification des codes E dans les additifs
        $additivesLower = array_map(
            static fn (string $a): string => mb_strtolower($a),
            $product->getAdditives(),
        );

        foreach (self::SWEETENER_E_CODES as $code) {
            foreach ($additivesLower as $additive) {
                if (str_contains($additive, $code)) {
                    $found[] = $code;
                    break;
                }
            }
        }

        // Vérification des mots-clés dans les ingrédients
        $ingredients = $product->getIngredientsRaw();
        if (null !== $ingredients && '' !== trim($ingredients)) {
            $ingredientsLower = mb_strtolower($ingredients);
            foreach (self::SWEETENER_KEYWORDS as $keyword) {
                if (str_contains($ingredientsLower, $keyword)) {
                    $found[] = $keyword;
                }
            }
        }

        if ([] === $found) {
            return null;
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
        );
    }
}
