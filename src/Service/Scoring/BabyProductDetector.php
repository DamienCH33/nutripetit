<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Product;

/**
 * Détecte si un produit est destiné aux nourrissons/enfants 0-3 ans.
 *
 * Combine 2 signaux : categories_tags OFF + mots-clés du nom.
 */
final class BabyProductDetector
{
    private const CATEGORY_TAGS = [
        // Aliments solides bébé
        'en:baby-foods',
        'en:baby-food',
        'en:baby-snacks',
        'en:baby-meals',
        'en:baby-cereals',
        'en:baby-purees',
        'en:baby-yogurts',
        'en:baby-drinks',
        'en:baby-juices',
        'en:weaning-foods',

        // Laits infantiles - âges
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

        // Laits infantiles - formulations médicales
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

        // Laits infantiles - origines
        'en:cow-milk-infant-formula',
        'en:goat-milk-infant-formula',
        'en:goat-milk-formula',
        'en:goat-milk-formulas',
        'en:plant-based-infant-formula',
        'en:soy-infant-formula',
        'en:soy-infant-formulas',
        'en:rice-infant-formula',
        'en:rice-infant-formulas',

        // Laits infantiles - formats
        'en:powdered-milks',
        'en:powdered-milk',
        'en:baby-milk-powder',
        'en:ready-to-feed-milk',
        'en:ready-to-feed-milks',
        'en:liquid-infant-formula',
        'en:liquid-infant-formulas',
    ];

    private const NAME_KEYWORDS = [
        // Génériques bébé
        'bébé',
        'bebe',
        'nourrisson',
        'infantile',
        'baby food',
        'infant',
        'petits pots',
        'petit pot',

        // Âges
        '1er âge',
        '1er age',
        '1 er age',
        '1 er âge',
        '1er mois',
        '2ème âge',
        '2eme age',
        '2e age',
        '2 e age',
        '2ème mois',
        '3ème âge',
        '3eme age',
        '3e age',
        '3 e age',
        'à partir de 4 mois',
        'à partir de 6 mois',
        'à partir de 8 mois',
        'à partir de 10 mois',
        'à partir de 12 mois',
        'partir de 4 mois',
        'partir de 6 mois',
        'partir de 8 mois',
        'partir de 10 mois',
        'partir de 12 mois',
        'dès 4 mois',
        'dès 6 mois',
        'dès 8 mois',
        'dès 10 mois',
        'dès 12 mois',
        'dès le 1er mois',
        'dès la naissance',
        'lait de croissance',
        'lait croissance',
        'lait infantile',
        'lait nourrisson',
        'préparation pour nourrisson',
        'preparation pour nourrisson',
        'préparation infantile',
        'preparation infantile',

        // Formulations médicales (acronymes officiels)
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
        'hypoallergénique',
        'hypoallergenique',
        'sans lactose',
        'lactose free',
        'lait confort',
        'transit',
        'bifidus',
        'lait relax',
        'lait satiété',
        'lait satiete',
        'lait pré',
        'lait pre',
        'lait prématuré',
        'lait premature',
        'aplv',
        'hydrolysé',
        'hydrolyse',
        'hydrolysat',
        'acides aminés',
        'acides amines',
        'amino acid',

        // Origines spéciales
        'lait de chèvre infantile',
        'lait chèvre bébé',
        'lait végétal infantile',

        // International
        'preparation pour nourrissons',
        'follow-on milk',
        'follow on milk',
        'growing-up milk',
        'growing up milk',
        'toddler milk',
        'formula',
        'infant formula',

        // Marques de laits infantiles connues
        'gallia',
        'galia',
        'guigoz',
        'nestlé nidal',
        'nidal',
        'blédilait',
        'bledilait',
        'blédina',
        'bledina',
        'novalac',
        'modilac',
        'picot',
        'enfamil',
        'similac',
        'aptamil',
        'milupa',
        'hipp',
        'babybio bio bébé',
        'holle',
        'lemiel',
        'biostime',
        'physiolac',
        'kendamil',
        'capricare',
        'sammy capricare',

        // Mots seuls détecteurs forts (laits)
        ' ar ',
        ' ar',
        'ar ',  // attention espaces, sinon "art", "bar" matchent
        ' ac ',
        ' ac',
        'ac ',
        ' ha ',
        ' ha',
        'ha ',
    ];
    /**
     * Tokens isolés (mot entier) — pour détecter AR, AC, HA sans matcher "art" ou "bar".
     */
    private const NAME_TOKENS = [
        'ar',
        'ac',
        'ha',
        'aplv',
        'gallia',
        'galia',
        'guigoz',
        'nidal',
        'novalac',
        'modilac',
        'picot',
        'enfamil',
        'similac',
        'aptamil',
        'milupa',
        'hipp',
        'holle',
        'physiolac',
        'kendamil',
        'capricare',
        'biostime',
        'lemiel',
        'blédilait',
        'bledilait',
        'blédina',
        'bledina',
        'babybio',
    ];

    public function isBabyProduct(Product $product): bool
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

        // Recherche substring classique
        foreach (self::NAME_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        // Recherche par tokens (mots isolés) pour AR, AC, HA, etc.
        $tokens = preg_split('/[\s\-_\.,]+/', $name) ?: [];
        $tokens = array_filter($tokens, static fn ($t) => '' !== $t);
        foreach (self::NAME_TOKENS as $token) {
            if (\in_array($token, $tokens, true)) {
                return true;
            }
        }

        return false;
    }
}
