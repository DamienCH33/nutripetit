<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence de sucres AJOUTÉS dans la liste d'ingrédients.
 *
 * Source : OMS Guideline: Sugars intake (2015) — zéro sucre ajouté chez le nourrisson.
 *
 * Deux garde-fous contre les faux positifs :
 *  1. Le label OFF en:no-added-sugar (ou fr:sans-sucres-ajoutes) fait foi :
 *     s'il est présent, aucun malus (OFF certifie l'absence de sucre ajouté).
 *  2. On neutralise les mentions négatives ("sans sucre(s) ajouté(s)") du texte
 *     avant la recherche, sinon le mot "sucre" de la négation déclenche le malus.
 */
final class AddedSugarsEvaluator implements RuleEvaluator
{
    private const ADDED_SUGAR_KEYWORDS = [
        'sucre',
        'saccharose',
        'sirop de glucose',
        'sirop de mais',
        'sirop de maïs',
        'sirop de fructose',
        'sirop d\'agave',
        'dextrose',
        'fructose',
        'maltose',
        'miel',
        'mélasse',
        'sirop d\'érable',
    ];

    /** Labels OFF certifiant l'absence de sucre ajouté. */
    private const NO_ADDED_SUGAR_LABELS = [
        'en:no-added-sugar',
        'en:no-added-sugars',
        'fr:sans-sucres-ajoutes',
        'fr:sans-sucre-ajoute',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'added_sugars' === $rule->getCode();
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

        // Garde-fou 1 : label OFF "sans sucre ajouté" → jamais de malus.
        $labels = $product->getOffRawData()['labels_tags'] ?? [];
        if (\is_array($labels)) {
            foreach (self::NO_ADDED_SUGAR_LABELS as $label) {
                if (\in_array($label, $labels, true)) {
                    return $this->satisfied($rule);
                }
            }
        }

        $ingredientsLower = mb_strtolower($ingredients);

        // Garde-fou 2 : retirer les mentions négatives avant de chercher les mots-clés,
        // sinon "sucre" dans "sans sucres ajoutés" déclenche un faux positif.
        $ingredientsLower = preg_replace(
            '/sans\s+sucres?\s+ajout[ée]s?/u',
            '',
            $ingredientsLower,
        ) ?? $ingredientsLower;

        $foundKeywords = [];
        foreach (self::ADDED_SUGAR_KEYWORDS as $keyword) {
            if (str_contains($ingredientsLower, $keyword)) {
                $foundKeywords[] = $keyword;
            }
        }

        if ([] === $foundKeywords) {
            return $this->satisfied($rule);
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: \sprintf(
                'Présence détectée dans la liste d\'ingrédients : %s',
                implode(', ', \array_slice($foundKeywords, 0, 3)),
            ),
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Triggered,
        );
    }

    private function satisfied(ScoringRule $rule): AppliedRuleDto
    {
        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: 'Sans sucre ajouté',
            pointsImpact: 0,
            reason: 'Aucun sucre ajouté détecté dans la liste d\'ingrédients.',
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Satisfied,
        );
    }
}
