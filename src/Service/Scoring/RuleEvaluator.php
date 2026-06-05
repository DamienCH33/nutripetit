<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;

/**
 * Contrat pour un évaluateur de règle de scoring.
 */
interface RuleEvaluator
{
    /**
     * Indique si cet évaluateur est responsable de la règle fournie.
     *
     * Permet au ScoreCalculator de déléguer chaque règle à son évaluateur dédié.
     */
    public function supports(ScoringRule $rule): bool;

    /**
     * Évalue si la règle se déclenche pour ce produit.
     */
    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto;
}
