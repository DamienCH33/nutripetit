<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Scoring\RuleEvaluator;

/**
 * Alerte critique : miel avant 12 mois = risque de botulisme infantile.
 * Source : ANSES « Pas de miel pour les enfants de moins d'un an » ; OMS ; SPF.
 * Les spores de Clostridium botulinum résistent à la cuisson. Se déclenche si
 * âge < 12 mois ou inconnu ; après 12 mois, le miel reste couvert par le malus
 * sucre. Le code 'honey_botulism' est dans CriticalAlertDetector (bandeau rouge).
 */
final class HoneyBotulismEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'honey_botulism' === $rule->getCode();
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

        $lower = mb_strtolower($ingredients);
        if (!str_contains($lower, 'miel') && !str_contains($lower, 'honey')) {
            return null;
        }

        if (null !== $babyAgeMonths && $babyAgeMonths >= 12) {
            return null;
        }

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: 'Contient du miel — interdit avant 1 an',
            pointsImpact: $rule->getPointsImpact(),
            reason: 'Le miel peut contenir des spores de Clostridium botulinum (botulisme infantile), y compris cuit. Aucun miel avant 12 mois, sous aucune forme.',
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
            status: RuleStatus::Triggered,
        );
    }
}
