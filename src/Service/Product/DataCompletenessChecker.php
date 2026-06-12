<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

/**
 * Si les données sont insuffisantes, l'UI doit afficher un état
 * "données insuffisantes" au lieu du score.
 */
final class DataCompletenessChecker
{
    public function hasSufficientData(Product $product): bool
    {
        $hasIngredients = null !== $product->getIngredientsRaw()
            && '' !== trim($product->getIngredientsRaw());

        $hasNutriments = [] !== array_filter(
            $product->getNutriments(),
            static fn ($v): bool => is_numeric($v),
        );

        return $hasIngredients || $hasNutriments;
    }
}
