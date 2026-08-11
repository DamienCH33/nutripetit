<?php

declare(strict_types=1);

namespace App\Service\Product;

final class CriticalAlertDetector
{
    /**
     * Codes déclenchant une alerte critique (danger aigu ou non-conformité
     * réglementaire). 'contaminated_fish_limit' et 'unqualified_flavors' NE
     * sont PAS ici (malus, pas alerte).
     */
    private const CRITICAL_RULE_CODES = [
        'choking_hazard',
        'contaminated_fish',
        'honey_botulism',
        'sweeteners',
        'artificial_flavors',
    ];

    /**
     * @param list<array<string, mixed>> $appliedRules
     *
     * @return array<string, string>|null
     */
    public function detect(array $appliedRules): ?array
    {
        foreach ($appliedRules as $rule) {
            if (($rule['status'] ?? 'triggered') === 'satisfied') {
                continue;
            }

            $code = $rule['rule_code'] ?? '';
            if (\in_array($code, self::CRITICAL_RULE_CODES, true)) {
                return [
                    'title' => $rule['rule_label'] ?? 'Alerte critique',
                    'message' => $rule['reason'] ?? '',
                    'source_name' => $rule['source_name'] ?? '',
                    'source_url' => $rule['source_url'] ?? '#',
                ];
            }
        }

        return null;
    }
}
