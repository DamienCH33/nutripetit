<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

/**
 * Source de vérité unique pour la détection de l'huile de palme dans la liste
 * d'ingrédients (score aliments, score laits, badge de la fiche).
 *
 * Mots entiers uniquement : « palmitate » (palmitate d'ascorbyle, de rétinyle :
 * vitamines présentes dans la plupart des laits) n'est PAS de l'huile de palme.
 * Les mentions négatives (« sans huile de palme ») sont retirées avant la recherche.
 */
final class PalmOilDetector
{
    private const PALM_OIL_PATTERN = '/\bpalm(?:e|iste|ier)s?\b|\bpalm\s+(?:kernel\s+)?(?:oil|fat)\b/u';

    private const NEGATION_PATTERN = '/sans\s+(?:huiles?|graisses?|matières?\s+grasses?)\s+de\s+palme/u';

    /**
     * @return bool|null null si la liste d'ingrédients est absente : on ne sait pas
     */
    public function containsPalmOil(Product $product): ?bool
    {
        $ingredients = $this->normalizedIngredients($product);

        return null === $ingredients ? null : $this->matches($ingredients);
    }

    /** Huile de palme dans le premier ingrédient (avant la première virgule). */
    public function isFirstIngredient(Product $product): bool
    {
        $ingredients = $this->normalizedIngredients($product);

        return null !== $ingredients && $this->matches(explode(',', $ingredients, 2)[0]);
    }

    private function normalizedIngredients(Product $product): ?string
    {
        $ingredients = $product->getIngredientsRaw();
        if (null === $ingredients || '' === trim($ingredients)) {
            return null;
        }

        return preg_replace(self::NEGATION_PATTERN, '', mb_strtolower($ingredients)) ?? mb_strtolower($ingredients);
    }

    private function matches(string $text): bool
    {
        return 1 === preg_match(self::PALM_OIL_PATTERN, $text);
    }
}
