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
    /** Assez de données pour calculer un score (ingrédients OU nutriments). */
    public function hasSufficientData(Product $product): bool
    {
        return $this->hasIngredients($product) || $this->hasNutriments($product);
    }

    /**
     * Toutes les règles sont évaluables (ingrédients ET nutriments).
     * Sinon le score est partiel : sans liste d'ingrédients, miel, sucres
     * ajoutés, édulcorants, additifs et allergènes ne peuvent pas être vus,
     * et le score ressort trop favorable. La fiche doit le dire.
     */
    public function isComplete(Product $product): bool
    {
        return $this->hasIngredients($product) && $this->hasNutriments($product);
    }

    private function hasIngredients(Product $product): bool
    {
        $ingredients = $product->getIngredientsRaw();

        return null !== $ingredients && '' !== trim($ingredients);
    }

    private function hasNutriments(Product $product): bool
    {
        return [] !== array_filter(
            $product->getNutriments(),
            static fn ($v): bool => is_numeric($v),
        );
    }
}
