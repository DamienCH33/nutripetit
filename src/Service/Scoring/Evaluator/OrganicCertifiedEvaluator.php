<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la certification Bio AB du produit (bonus +5 points).
 *
 * Source : Règlement EU 2018/848 - Agence Bio
 * Le label AB garantit l'absence de pesticides de synthèse et d'OGM.
 */
final class OrganicCertifiedEvaluator implements RuleEvaluator
{
    private const ORGANIC_KEYWORDS = [
        'bio',
        'biologique',
        'agriculture biologique',
        'organic',
        'eu-organic',
        'label ab',
        'agriculture-biologique',
        'fr-bio',
        'bio-ab',
    ];

    public function supports(ScoringRule $rule): bool
    {
        return 'organic_certified' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        // Recherche dans les données brutes OFF
        $offData = $product->getOffRawData();
        $labelsTags = $offData['labels_tags'] ?? [];

        if (!\is_array($labelsTags)) {
            $labelsTags = [];
        }

        $labelsLower = array_map(
            static fn ($l): string => \is_string($l) ? mb_strtolower($l) : '',
            $labelsTags,
        );

        // Vérification dans les labels OFF
        foreach (self::ORGANIC_KEYWORDS as $keyword) {
            foreach ($labelsLower as $label) {
                if (str_contains($label, $keyword)) {
                    return $this->createAppliedRule($rule, "Label détecté : {$label}");
                }
            }
        }

        // Fallback
        $nameLower = mb_strtolower($product->getName());
        foreach (self::ORGANIC_KEYWORDS as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return $this->createAppliedRule($rule, 'Mention bio détectée dans le nom du produit');
            }
        }

        return null;
    }

    private function createAppliedRule(ScoringRule $rule, string $reason): AppliedRuleDto
    {
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
