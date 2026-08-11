<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Product\OrganicLabelDetector;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la certification Bio du produit (bonus).
 *
 * Source : Règlement UE 2018/848 - Agence Bio.
 * La détection se fonde uniquement sur les labels certifiants d'Open Food
 * Facts (via OrganicLabelDetector), jamais sur le nom commercial : une
 * mention marketing « bio » n'est pas une certification.
 */
final class OrganicCertifiedEvaluator implements RuleEvaluator
{
    public function __construct(
        private readonly OrganicLabelDetector $organicLabelDetector,
    ) {
    }

    public function supports(ScoringRule $rule): bool
    {
        return 'organic_certified' === $rule->getCode();
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        if (!$this->organicLabelDetector->isOrganic($product)) {
            return null;
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: 'Certification biologique détectée (label Open Food Facts).',
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
        );
    }
}
