<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Product;

/**
 * Détecte si un produit est destiné aux nourrissons/enfants 0-3 ans.
 *
 * Combine 5 signaux : catégories bébé OFF, marques 100% bébé, mots-clés du nom,
 * tokens isolés (acronymes médicaux), et catégories d'aliments de base bébé-compatibles.
 */
final class BabyProductDetector implements BabyProductDetectorInterface
{
    /**
     * Marqueurs (normalisés : minuscules, sans accents) cherchés DANS les tags
     * catégories mal encodés. Volontairement stricts et sans ambiguïté :
     * "pour bebe", "bebes", "nourrisson" ne se retrouvent pas dans un tag de
     * produit adulte. Ne PAS ajouter "lait" ou "bio" seuls -> faux positifs.
     */
    private const CATEGORY_KEYWORDS = [
        'pour bebe',
        'baby-food',
        'baby food',
        'nourrisson',
        'infant-formula',
        'infant formula',
    ];

    private const CATEGORY_TAGS = [
        // Aliments solides bébé (EN)
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

        // Aliments solides bébé (FR) — OFF ne traduit pas toujours
        'fr:gateaux-pour-bebe',
        'fr:aliments-pour-bebe',
        'fr:desserts-pour-bebe',
        'fr:cereales-pour-bebe',
        'fr:plats-pour-bebe',
        'fr:petits-pots',
        'fr:repas-pour-bebe',

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

    /**
     * Marques exclusivement destinées aux 0-3 ans.
     * Signal très fiable : ces marques ne font que du bébé.
     * NE PAS ajouter de marques généralistes (Nestlé, Danone…) = faux positifs.
     * Comparées en minuscules sans accents.
     */
    private const BABY_BRANDS = [
        'hipp',
        'bledina',
        'bledilait',
        'gallia',
        'guigoz',
        'nidal',
        'modilac',
        'novalac',
        'picot',
        'physiolac',
        'babybio',
        'holle',
        'aptamil',
        'milupa',
        'kendamil',
        'capricare',
        'biostime',
        'lemiel',
        'good gout',
        'goodgout',
        'good goût',
        'la marmite-bebe',
        'yooji',
        'pommette',
        'babynat',
        'vitabio',
        'nutriben',
        'bledidej',
        'naturnes',
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
        'céréales croissance',
        'cereales croissance',
        'bledidej',
        'blédidej',
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
        'lait sans lactose',
        'lait confort',
        'lait transit',
        'lait bifidus',
        'lait relax',
        'lait satiété',
        'lait satiete',
        'lait pré',
        'lait pre',
        'lait prématuré',
        'lait premature',
        'aplv',
        'lait hydrolysé',
        'lait hydrolyse',
        'hydrolysat de protéines de lait',
        'formule aux acides aminés',
        'amino acid formula',

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
        'infant formula',
        'follow-on formula',
        'follow on formula',
        'toddler formula',

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

        // NOTE : les acronymes AR / AC / HA sont détectés UNIQUEMENT via
        // NAME_TOKENS (Signal 4, mots isolés). Ne jamais les remettre ici en
        // sous-chaînes : ' ar' matchait "artisanal", 'ha ' matchait "matcha",
        // ' ac' matchait "acacia" -> des produits adultes passaient la garde.
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

    /**
     * Catégories d'aliments transformés simples que les bébés consomment
     * couramment (compotes, purées de fruits/légumes). Rattrape les vrais
     * produits bébé qu'OFF catégorise sans tag "baby" (ex. marque Popote
     * rangée en "en:compotes"). Badge « indicatif », pas « vérifié ».
     * NE PAS y mettre en:fruits / en:vegetables (trop larges, tout le primeur).
     */
    private const BABY_COMPATIBLE_CATEGORIES = [
        'en:compotes',
        'en:applesauces',
        'en:fruit-sauces',
        'en:purees',
    ];

    public function isBabyProduct(Product $product): bool
    {
        $raw = $product->getOffRawData();

        // Signal 1 : catégories OFF (EN + FR), correspondance exacte.
        $categories = $raw['categories_tags'] ?? [];
        if (\is_array($categories)) {
            foreach (self::CATEGORY_TAGS as $tag) {
                if (\in_array($tag, $categories, true)) {
                    return true;
                }
            }

            // Signal 1bis : certains produits OFF ont des tags mal formés —
            // préfixe "en:" mais texte français accentué et en majuscules,
            // ex. "en:Aliments pour bébé", "en:Plats du soir pour bébé".
            // On normalise chaque tag (minuscules, sans accents) et on cherche
            // des marqueurs bébé, pour rattraper ces cas.
            foreach ($categories as $tag) {
                if (!\is_string($tag)) {
                    continue;
                }
                $normalized = $this->normalize($tag);
                foreach (self::CATEGORY_KEYWORDS as $marker) {
                    if (str_contains($normalized, $marker)) {
                        return true;
                    }
                }
            }
        }

        // Signal 2 : marque exclusivement bébé (très fiable).
        $brands = $raw['brands_tags'] ?? [];
        if (\is_array($brands)) {
            foreach ($brands as $brand) {
                if (!\is_string($brand)) {
                    continue;
                }
                $normalized = $this->normalize($brand);
                if (\in_array($normalized, self::BABY_BRANDS, true)) {
                    return true;
                }
            }
        }

        $name = mb_strtolower($product->getName());

        // Signal 2bis : marque CONTENANT "baby"/"bebe" (gammes bébé de distributeurs :
        // Carrefour Baby, Auchan Baby, U Bébé… qu'OFF ne catégorise pas en baby-food).
        if (\is_array($brands)) {
            foreach ($brands as $brand) {
                if (!\is_string($brand)) {
                    continue;
                }
                $normalized = $this->normalize($brand);
                if (str_contains($normalized, 'baby') || str_contains($normalized, 'bebe')) {
                    return true;
                }
            }
        }

        // Signal 3 : recherche substring dans le nom.
        foreach (self::NAME_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        // Signal 4 : recherche par tokens (mots isolés) pour AR, AC, HA, etc.
        $tokens = preg_split('/[\s\-_\.,]+/', $name) ?: [];
        $tokens = array_filter($tokens, static fn ($t) => '' !== $t);
        foreach (self::NAME_TOKENS as $token) {
            if (\in_array($token, $tokens, true)) {
                return true;
            }
        }

        // Signal 4bis : acronymes médicaux AR / AC / HA — uniquement si le nom
        // contient aussi un mot de contexte lait (sinon "ha", "ar", "ac" isolés
        // matchent des produits adultes : thé "Ha Long", vin "AC"…).
        $hasMilkContext = str_contains($name, 'lait') || str_contains($name, 'milk');
        if ($hasMilkContext) {
            foreach (['ar', 'ac', 'ha'] as $medicalToken) {
                if (\in_array($medicalToken, $tokens, true)) {
                    return true;
                }
            }
        }

        // Signal 5 : aliment de base transformé bébé-compatible (compotes, purées).
        // Rattrape les produits bébé mal catégorisés dans OFF (sans tag "baby").
        if (\is_array($categories)) {
            foreach (self::BABY_COMPATIBLE_CATEGORIES as $tag) {
                if (\in_array($tag, $categories, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalise une marque : minuscules, sans accents, sans espaces superflus.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $map = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ü' => 'u', 'ç' => 'c'];

        return strtr($value, $map);
    }
}
