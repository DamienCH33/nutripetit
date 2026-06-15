<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\RuleStatus;

/**
 * Représente une règle de scoring évaluée pour un produit.
 *
 * - status Triggered : la règle impacte le score (malus/bonus).
 * - status Satisfied : contrôle passé (ex. "Sans sucre ajouté"), impact nul.
 */
final readonly class AppliedRuleDto
{
    public function __construct(
        public string $ruleCode,
        public string $ruleLabel,
        public int $pointsImpact,
        public string $reason,
        public string $sourceName,
        public string $sourceUrl,
        public RuleStatus $status = RuleStatus::Triggered,
    ) {}

    /**
     * Sérialisation pour stockage JSON dans ScoreResult.appliedRules.
     *
     * @return array{rule_code: string, rule_label: string, points: int, reason: string, source_name: string, source_url: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'rule_code' => $this->ruleCode,
            'rule_label' => $this->ruleLabel,
            'points' => $this->pointsImpact,
            'reason' => $this->reason,
            'source_name' => $this->sourceName,
            'source_url' => $this->sourceUrl,
            'status' => $this->status->value,
        ];
    }
}
