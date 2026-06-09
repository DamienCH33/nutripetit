<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

final class MinimumAgeExtractor
{
    public function extractMinAgeMonths(Product $product): ?int
    {
        $tags = array_merge(
            $product->getOffRawData()['categories_tags'] ?? [],
            $product->getOffRawData()['labels_tags'] ?? [],
        );

        foreach ($tags as $tag) {
            $tag = strtolower((string) $tag);
            if (preg_match('/des?-(\d+)-(?:month|mois)/', $tag, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/from-(\d+)-(?:month|mois)/', $tag, $m)) {
                return (int) $m[1];
            }
        }

        $name = mb_strtolower($product->getName());
        if (preg_match('/d[èe]s (\d+) mois/', $name, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
