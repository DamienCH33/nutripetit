<?php

declare(strict_types=1);

namespace App\Service\Product;

final class RuleSourceAggregator
{
    /**
     * @param list<array<string, mixed>> $appliedRules
     *
     * @return list<array<string, mixed>>
     */
    public function aggregate(array $appliedRules): array
    {
        $unique = [];

        foreach ($appliedRules as $rule) {
            $name = $rule['source_name'] ?? null;
            if (!\is_string($name) || '' === $name) {
                continue;
            }
            if (!isset($unique[$name])) {
                $unique[$name] = [
                    'name' => $name,
                    'url' => $rule['source_url'] ?? '#',
                    'rules' => [],
                ];
            }
            $unique[$name]['rules'][] = $rule['rule_label'] ?? '';
        }

        return array_values($unique);
    }
}
