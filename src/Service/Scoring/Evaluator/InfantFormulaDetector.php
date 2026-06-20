<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Entity\Product;

/**
 * Détecte si un Product est un lait infantile.
 *
 * 2 signaux combinés : categories_tags OFF + mots-clés explicites de lait dans le nom.
 *
 * IMPORTANT : on ne se base PAS sur la marque (Hipp, Blédina, Gallia…) pour ce détecteur.
 * Ces marques produisent aussi des compotes, petits pots, biscuits… La marque indique
 * « produit bébé » (cf. BabyProductDetector), pas « lait ». Utiliser la marque ici
 * classerait une compote Hipp comme un lait infantile (faux positif).
 */
final class InfantFormulaDetector
{
    private const CATEGORY_TAGS = [
        // Âges
        'en:baby-milks',
        'en:baby-milk',
        'en:infant-formulas',
        'en:infant-formula',
        'en:infant-formula-milks',
        'en:infant-formula-milk',
        'en:infant-milks',
        'en:infant-milk',
        'en:milks-for-infants',
        'en:follow-on-formula',
        'en:follow-on-formulas',
        'en:follow-on-milk',
        'en:follow-on-milks',
        'en:growing-up-milk',
        'en:growing-up-milks',
        'en:toddler-milk',
        'en:toddler-milks',
        'en:toddler-formula',
        'en:toddler-formulas',
        'en:premature-formula',
        'en:premature-formulas',
        'en:premature-milks',
        'en:pre-formula',
        'en:pre-formulas',

        // Formulations médicales
        'en:anti-regurgitation-milk',
        'en:anti-regurgitation-milks',
        'en:anti-colic-milk',
        'en:anti-colic-milks',
        'en:hypoallergenic-milk',
        'en:hypoallergenic-milks',
        'en:hypoallergenic-formula',
        'en:hypoallergenic-formulas',
        'en:lactose-free-milk',
        'en:lactose-free-milks',
        'en:lactose-free-infant-formula',
        'en:comfort-milk',
        'en:comfort-milks',
        'en:bifidus-milk',
        'en:bifidus-milks',
        'en:transit-milk',
        'en:transit-milks',
        'en:relax-milk',
        'en:relax-milks',
        'en:satiety-milk',
        'en:satiety-milks',
        'en:thickened-milk',
        'en:thickened-milks',
        'en:hydrolysed-formula',
        'en:hydrolysed-formulas',
        'en:extensively-hydrolysed-formula',
        'en:extensively-hydrolysed-formulas',
        'en:amino-acid-formula',
        'en:amino-acid-formulas',

        // Origines
        'en:cow-milk-infant-formula',
        'en:goat-milk-infant-formula',
        'en:goat-milk-formula',
        'en:goat-milk-formulas',
        'en:plant-based-infant-formula',
        'en:soy-infant-formula',
        'en:soy-infant-formulas',
        'en:rice-infant-formula',
        'en:rice-infant-formulas',

        // Formats
        'en:powdered-milks',
        'en:powdered-milk',
        'en:baby-milk-powder',
        'en:ready-to-feed-milk',
        'en:ready-to-feed-milks',
        'en:liquid-infant-formula',
        'en:liquid-infant-formulas',
    ];

    /**
     * Mots-clés EXPLICITES de lait infantile (pas de marque ici).
     */
    private const NAME_KEYWORDS = [
        // Génériques
        'lait infantile',
        'lait nourrisson',
        'préparation pour nourrisson',
        'preparation pour nourrisson',
        'préparation infantile',
        'preparation infantile',
        'lait de croissance',
        'lait croissance',

        // Âges (toujours pr\u00e9fix\u00e9s par "lait" pour \u00e9viter de matcher un petit pot "2e \u00e2ge")
        'lait 1er âge',
        'lait 1er age',
        'lait 2ème âge',
        'lait 2eme age',
        'lait 2e age',
        'lait 3ème âge',
        'lait 3eme age',
        'lait 3e age',

        // Formulations médicales
        'lait ar',
        'lait a.r',
        'anti-régurgitation',
        'anti regurgitation',
        'lait ac',
        'lait a.c',
        'anti-colique',
        'anti colique',
        'lait ha',
        'lait h.a',
        'lait hypoallergénique',
        'lait hypoallergenique',
        'lait sans lactose',
        'lait confort',
        'lait transit',
        'lait bifidus',
        'lait relax',
        'lait satiété',
        'lait satiete',
        'lait pré',
        'lait prématuré',
        'lait premature',
        'lait hydrolysé',
        'lait hydrolyse',

        // International
        'infant formula',
        'follow-on milk',
        'follow on milk',
        'growing-up milk',
        'growing up milk',
        'toddler milk',
    ];

    public function isInfantFormula(Product $product): bool
    {
        $raw = $product->getOffRawData();
        $categories = $raw['categories_tags'] ?? [];

        if (\is_array($categories)) {
            foreach (self::CATEGORY_TAGS as $tag) {
                if (\in_array($tag, $categories, true)) {
                    return true;
                }
            }
        }

        $name = mb_strtolower($product->getName());
        foreach (self::NAME_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
