<?php

namespace App\Service\Product;

use App\Entity\Product;

final class CarbonFootprintExtractor
{
    /**
     * @return array{value: float, unit: string, car_km: float}|null
     */
    public function extractCarbonFootprint(Product $product): ?array
    {
        $raw = $product->getOffRawData();
        $nutriments = $raw['nutriments'] ?? [];

        $candidates = [
            'carbon-footprint-from-known-ingredients_100g',
            'carbon-footprint_100g',
        ];

        foreach ($candidates as $key) {
            $value = $nutriments[$key] ?? null;
            if (is_numeric($value)) {
                $v = (float) $value;

                return [
                    'value' => round($v, 1),
                    'unit' => 'g CO₂e/100g',
                    'car_km' => round($v / 200, 2),
                ];
            }
        }

        $ecoCo2 = $raw['ecoscore_data']['agribalyse']['co2_total'] ?? null;
        if (is_numeric($ecoCo2)) {
            $v = (float) $ecoCo2 * 1000;

            return [
                'value' => round($v, 1),
                'unit' => 'g CO₂e/kg',
                'car_km' => round($v / 200, 2),
            ];
        }

        return null;
    }
}
