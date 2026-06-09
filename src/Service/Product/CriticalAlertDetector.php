<?php

declare(strict_types=1);

namespace App\Service\Product;

final class CriticalAlertDetector
{
    /**
     * @param list<array<string, mixed>> $appliedRules
     *
     * @return array<string, string>|null
     */
    public function detect(array $appliedRules): ?array
    {
        foreach ($appliedRules as $rule) {
            $code = $rule['rule_code'] ?? '';

            if (\in_array($code, ['choking_hazard', 'contaminated_fish'], true)) {
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
