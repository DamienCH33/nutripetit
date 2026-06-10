<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

final class NutrientViewBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function buildNutrients(Product $product, bool $isInfantFormula = false): array
    {
        $n = $product->getNutriments();

        if ($isInfantFormula) {
            return $this->buildInfantFormulaNutrients($n);
        }

        return $this->buildBabyFoodNutrients($n);
    }

    /**
     * @param array<string, mixed> $n
     *
     * @return list<array<string, mixed>>
     */
    private function buildInfantFormulaNutrients(array $n): array
    {
        $criteria = [
            ['key' => 'energy-kcal_100g', 'name' => 'Énergie', 'unit' => 'kcal', 'min' => 60.0, 'max' => 70.0, 'maxScale' => 80.0, 'source' => 'UE 2016/127', 'category' => 'Macronutriments'],
            ['key' => 'proteins_100g', 'name' => 'Protéines', 'unit' => 'g', 'min' => 1.2, 'max' => 1.8, 'idealMax' => 1.34, 'maxScale' => 3.0, 'source' => 'UE 2016/127 / SFP', 'category' => 'Macronutriments'],
            ['key' => 'fat_100g', 'name' => 'Lipides', 'unit' => 'g', 'min' => 4.4, 'max' => 6.0, 'maxScale' => 7.0, 'source' => 'UE 2016/127', 'category' => 'Macronutriments'],
            ['key' => 'carbohydrates_100g', 'name' => 'Glucides', 'unit' => 'g', 'min' => 9.0, 'max' => 14.0, 'maxScale' => 16.0, 'source' => 'UE 2016/127', 'category' => 'Macronutriments'],
            ['key' => 'dha_100g', 'name' => 'DHA (Oméga 3)', 'unit' => 'mg', 'min' => 0.020, 'max' => 0.050, 'maxScale' => 0.080, 'source' => 'UE 2016/127 (obligatoire)', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'arachidonic-acid_100g', 'name' => 'ARA (Oméga 6)', 'unit' => 'mg', 'min' => 0.020, 'max' => 0.060, 'maxScale' => 0.080, 'source' => 'EAP/CHF 2020', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'linoleic-acid_100g', 'name' => 'Acide linoléique', 'unit' => 'mg', 'min' => 0.35, 'max' => 0.84, 'maxScale' => 1.5, 'source' => 'UE 2016/127', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'alpha-linolenic-acid_100g', 'name' => 'Acide α-linolénique', 'unit' => 'mg', 'min' => 0.035, 'max' => 0.070, 'maxScale' => 0.150, 'source' => 'UE 2016/127', 'category' => 'Acides gras', 'multiplier' => 1000],
            ['key' => 'sodium_100g', 'name' => 'Sodium', 'unit' => 'mg', 'min' => 0.013, 'max' => 0.024, 'maxScale' => 0.060, 'source' => 'mpedia.fr / UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'iron_100g', 'name' => 'Fer', 'unit' => 'mg', 'min' => 0.0003, 'max' => 0.0013, 'maxScale' => 0.002, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'calcium_100g', 'name' => 'Calcium', 'unit' => 'mg', 'min' => 0.050, 'max' => 0.140, 'maxScale' => 0.200, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'phosphorus_100g', 'name' => 'Phosphore', 'unit' => 'mg', 'min' => 0.025, 'max' => 0.100, 'maxScale' => 0.140, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'iodine_100g', 'name' => 'Iode', 'unit' => 'µg', 'min' => 0.000015, 'max' => 0.000029, 'maxScale' => 0.000060, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000000],
            ['key' => 'zinc_100g', 'name' => 'Zinc', 'unit' => 'mg', 'min' => 0.0005, 'max' => 0.001, 'maxScale' => 0.002, 'source' => 'UE 2016/127', 'category' => 'Minéraux', 'multiplier' => 1000],
            ['key' => 'vitamin-d_100g', 'name' => 'Vitamine D', 'unit' => 'µg', 'min' => 0.000002, 'max' => 0.000003, 'maxScale' => 0.000005, 'source' => 'UE 2016/127', 'category' => 'Vitamines', 'multiplier' => 1000000],
            ['key' => 'vitamin-a_100g', 'name' => 'Vitamine A', 'unit' => 'µg', 'min' => 0.00006, 'max' => 0.00018, 'maxScale' => 0.00030, 'source' => 'UE 2016/127', 'category' => 'Vitamines', 'multiplier' => 1000000],
            ['key' => 'vitamin-c_100g', 'name' => 'Vitamine C', 'unit' => 'mg', 'min' => 0.004, 'max' => 0.030, 'maxScale' => 0.050, 'source' => 'UE 2016/127', 'category' => 'Vitamines', 'multiplier' => 1000],
        ];

        $result = [];
        foreach ($criteria as $c) {
            $preparedKey = str_replace('_100g', '_prepared_100g', $c['key']);
            $rawValue = $n[$preparedKey] ?? $n[$c['key']] ?? null;

            if (!is_numeric($rawValue)) {
                $result[] = [
                    'name' => $c['name'],
                    'category' => $c['category'],
                    'available' => false,
                    'value' => null,
                    'unit' => $c['unit'],
                    'threshold_baby' => $c['max'],
                    'max_scale' => $c['maxScale'],
                    'level' => 'unknown',
                    'message' => 'Donnée non disponible sur Open Food Facts.',
                    'reference' => \sprintf('Plage légale : %s à %s %s/100ml', $c['min'], $c['max'], $c['unit']),
                ];
                continue;
            }

            $value = (float) $rawValue;
            $displayValue = isset($c['multiplier']) ? $value * $c['multiplier'] : $value;
            $displayMin = isset($c['multiplier']) ? $c['min'] * $c['multiplier'] : $c['min'];
            $displayMax = isset($c['multiplier']) ? $c['max'] * $c['multiplier'] : $c['max'];
            $displayMaxScale = isset($c['multiplier']) ? $c['maxScale'] * $c['multiplier'] : $c['maxScale'];

            $level = match (true) {
                $value < $c['min'] => 'limit',
                $value <= ($c['idealMax'] ?? $c['max']) => 'ideal',
                $value <= $c['max'] => 'good',
                default => 'occasional',
            };

            if ('ideal' === $level) {
                $message = 'Valeur optimale selon les recommandations.';
            } elseif ('good' === $level) {
                $message = 'Valeur conforme au cadre légal.';
            } elseif ('limit' === $level) {
                $message = $value < $c['min']
                    ? 'En dessous du seuil légal minimum.'
                    : 'Au-dessus de la plage recommandée.';
            } else {
                $message = 'Au-dessus du seuil légal maximum.';
            }

            $result[] = [
                'name' => $c['name'],
                'category' => $c['category'],
                'available' => true,
                'value' => round($displayValue, 4),
                'unit' => $c['unit'],
                'threshold_baby' => $displayMax,
                'max_scale' => $displayMaxScale,
                'level' => $level,
                'message' => $message,
                'reference' => \sprintf('Plage légale : %s à %s %s/100ml (source : %s)', $displayMin, $displayMax, $c['unit'], $c['source']),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $n
     *
     * @return list<array<string, mixed>>
     */
    private function buildBabyFoodNutrients(array $n): array
    {
        $thresholds = [
            ['key' => 'sugars_100g', 'name' => 'Sucres', 'unit' => 'g', 'threshold' => 4.0, 'max' => 40.0],
            ['key' => 'salt_100g', 'name' => 'Sel', 'unit' => 'g', 'threshold' => 0.3, 'max' => 1.0],
            ['key' => 'proteins_100g', 'name' => 'Protéines', 'unit' => 'g', 'threshold' => 15.0, 'max' => 25.0],
            ['key' => 'energy-kcal_100g', 'name' => 'Calories', 'unit' => 'kcal', 'threshold' => 400.0, 'max' => 800.0],
        ];

        $result = [];
        foreach ($thresholds as $t) {
            $rawValue = $n[$t['key']] ?? null;
            if (!is_numeric($rawValue)) {
                continue;
            }
            $value = (float) $rawValue;
            $ratio = $value / $t['threshold'];
            $level = match (true) {
                $ratio <= 0.5 => 'ideal',
                $ratio <= 1.0 => 'good',
                $ratio <= 1.5 => 'occasional',
                $ratio <= 2.5 => 'limit',
                default => 'discouraged',
            };

            $result[] = [
                'name' => $t['name'],
                'category' => 'Nutrition',
                'available' => true,
                'value' => $value,
                'unit' => $t['unit'],
                'threshold_baby' => $t['threshold'],
                'max_scale' => $t['max'],
                'level' => $level,
                'message' => $value <= $t['threshold']
                    ? 'Conforme aux recommandations ANSES nourrisson.'
                    : 'Au-dessus des recommandations ANSES nourrisson.',
                'reference' => \sprintf('ANSES nourrisson : %s%s/100g max', $t['threshold'], $t['unit']),
            ];
        }

        return $result;
    }
}
