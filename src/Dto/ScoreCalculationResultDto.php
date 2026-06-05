<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Résultat complet d'un calcul de score.
 */
final readonly class ScoreCalculationResultDto
{
    /**
     * @param list<AppliedRuleDto> $appliedRules
     */
    public function __construct(
        public int $finalScore,
        public string $level,
        public array $appliedRules,
        public string $algoVersion,
        public ?int $babyAgeMonths = null,
    ) {
    }
}
