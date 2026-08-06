<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

final class NutrientViewBuilder
{
    /**
     * Valeurs nutritionnelles de RÉFÉRENCE pour laits infantiles reconstitués (/100 ml).
     * Source : table Ciqual 2025 (ANSES) — aliments 19013 / 19014 / 19012, fiabilité « A ».
     *
     * Raison d'être : Open Food Facts stocke pour les laits les valeurs de la POUDRE
     * (~×8 vs reconstitué), non comparables aux repères réglementaires (exprimés en
     * reconstitué). On NE construit donc PAS de tableau nutritionnel par produit pour les
     * laits : d'une part la donnée OFF est fausse, d'autre part une moyenne de catégorie
     * ne serait pas discriminante (identique pour toutes les marques d'un même âge). À la
     * place, le front affiche un encart pédagogique alimenté par buildInfantFormulaReference().
     *
     * @var array<string, array<string, float>>
     */
    private const CIQUAL_INFANT_REFERENCE = [
        'first_age' => [   // Lait 1er âge, prêt à consommer (Ciqual 19013)
            'energy' => 71.0,
            'proteins' => 1.2,
        ],
        'second_age' => [  // Lait 2e âge, prêt à consommer (Ciqual 19014)
            'energy' => 65.0,
            'proteins' => 1.2,
        ],
        'growing_up' => [  // Lait de croissance / 3ème âge, liquide (Ciqual 19012)
            'energy' => 61.0,
            'proteins' => 1.3,
        ],
    ];

    /**
     * Libellé humain de chaque type, pour l'encart front ("un lait de 1er âge…").
     *
     * @var array<string, string>
     */
    private const MILK_TYPE_LABELS = [
        'first_age' => 'de 1er âge',
        'second_age' => 'de 2e âge',
        'growing_up' => 'de croissance',
    ];

    /**
     * Tags OFF (canoniques, vérifiés sur taxonomies/food/categories.txt) d'un lait de
     * CROISSANCE / 3ème âge. Testés EN PREMIER : les "follow-on from 2/3 years" contiennent
     * "follow-on" mais sont des laits de croissance, pas des 2e âge.
     */
    private const GROWING_UP_TAGS = [
        'en:growth-milks',                        // vrai tag OFF (PAS en:growing-up-milk)
        'en:ready-to-feed-baby-growing-up-milk',
        'en:baby-follow-on-milk-from-2-years',
        'en:baby-follow-on-milk-from-3-years',
    ];

    /**
     * Tags OFF d'un lait 2e âge / préparation de suite.
     */
    private const SECOND_AGE_TAGS = [
        'en:second-age-baby-milk-powder',
        'en:follow-on-milk',
        'en:follow-on-milks',
        'en:ready-to-feed-baby-follow-on-milk',
        'en:baby-follow-on-milk-from-5-months',
        'en:baby-start-milk-from-10-months',      // 10 mois = 2e âge
    ];

    /**
     * Tableau nutritionnel affiché.
     *
     * Pour un LAIT : renvoie [] — pas de tableau. Les valeurs OFF concernent la poudre
     * (non comparables aux repères en reconstitué) et une moyenne de catégorie ne serait
     * pas discriminante. Le front affiche un encart via buildInfantFormulaReference().
     *
     * Pour un ALIMENT bébé : tableau classique avec seuils ANSES/OMS (valeurs OFF fiables).
     *
     * @return list<array<string, mixed>>
     */
    public function buildNutrients(Product $product, bool $isInfantFormula = false): array
    {
        if ($isInfantFormula) {
            return [];
        }

        return $this->buildBabyFoodNutrients($product->getNutriments());
    }

    /**
     * Données de l'encart nutritionnel d'un lait (affiché à la place du tableau).
     *
     * Renvoie le type de lait détecté + les valeurs Ciqual reconstituées correspondantes,
     * pour que le front écrive une phrase adaptée à l'âge ("un lait de 2e âge reconstitué
     * apporte environ 65 kcal et 1,2 g de protéines / 100 ml"). Valeurs indicatives, moyennes
     * de catégorie (référence Ciqual 2025), pas une mesure du produit précis.
     *
     * @return array{type: string, label: string, energy: float, proteins: float}
     */
    public function buildInfantFormulaReference(Product $product): array
    {
        $type = $this->detectMilkType($product);
        $ref = self::CIQUAL_INFANT_REFERENCE[$type] ?? self::CIQUAL_INFANT_REFERENCE['first_age'];

        return [
            'type' => $type,
            'label' => self::MILK_TYPE_LABELS[$type] ?? 'infantile',
            'energy' => $ref['energy'],
            'proteins' => $ref['proteins'],
        ];
    }

    /**
     * Détermine l'ÂGE du lait (1er / 2e / croissance) pour choisir le profil Ciqual.
     *
     * On ne détecte QUE l'âge, pas la spécialité (AR, AC, HA, sans lactose, prématuré…) :
     * ces laits respectent le même règlement UE 2016/127 et ont un profil nutritionnel
     * global proche du lait de base de leur âge.
     *
     * Tags OFF vérifiés contre la taxonomie réelle (taxonomies/food/categories.txt).
     * Découvertes du croisement : le tag croissance est en:growth-milks (pas
     * en:growing-up-milk) ; les follow-on "from 2/3 years" sont des laits de croissance ;
     * certains produits ont des tags contradictoires (ex. Guigoz 1er âge tagué À LA FOIS
     * en:infant-formulas ET en:growth-milks) — d'où la priorité au nom.
     *
     * Ordre : 1) mention d'âge explicite dans le nom (source la plus fiable) ;
     *         2) tags OFF en secours si le nom est muet ; 3) défaut 1er âge.
     *
     * Piège géré : "Relais" (Gallia Calisma Relais 1er âge) ne signifie PAS 2e âge —
     * c'est le CHIFFRE ou la mention d'âge qui tranche, jamais "relais" seul.
     */
    private function detectMilkType(Product $product): string
    {
        $raw = $product->getOffRawData();
        $categories = $raw['categories_tags'] ?? [];
        if (!\is_array($categories)) {
            $categories = [];
        }
        $name = mb_strtolower($product->getName());

        // ========== PRIORITÉ 1 : mention d'âge EXPLICITE dans le nom ==========

        // Croissance / 3ème âge
        if (
            str_contains($name, 'croissance')
            || str_contains($name, '3eme age') || str_contains($name, '3ème âge')
            || str_contains($name, '3e age') || str_contains($name, '3e âge')
            || str_contains($name, '3 eme age') || str_contains($name, '3ème age')
            || $this->hasAgeToken($name, '3')
        ) {
            return 'growing_up';
        }

        // 1er âge (testé AVANT le 2e pour capter "1er âge" sans ambiguïté)
        if (
            str_contains($name, '1er age') || str_contains($name, '1er âge')
            || str_contains($name, '1 er age') || str_contains($name, '1 er âge')
            || str_contains($name, '1ere age') || str_contains($name, '1ère âge')
            || $this->hasAgeToken($name, '1')
        ) {
            return 'first_age';
        }

        // 2e âge / relais / suite
        if (
            str_contains($name, '2eme age') || str_contains($name, '2ème âge')
            || str_contains($name, '2e age') || str_contains($name, '2e âge')
            || str_contains($name, '2ème age') || str_contains($name, '2 eme age')
            || str_contains($name, 'relais 2') || str_contains($name, 'suite')
            || $this->hasAgeToken($name, '2')
        ) {
            return 'second_age';
        }

        // ========== PRIORITÉ 2 : tags OFF (secours, si le nom ne dit rien) ==========
        if ($this->hasAnyTag($categories, self::GROWING_UP_TAGS)) {
            return 'growing_up';
        }
        if ($this->hasAnyTag($categories, self::SECOND_AGE_TAGS)) {
            return 'second_age';
        }

        // ========== DÉFAUT : 1er âge ==========
        return 'first_age';
    }

    /**
     * Détecte le chiffre d'âge (1/2/3) comme token isolé dans le nom, en IGNORANT
     * les chiffres parasites : poids ("830g"), volumes ("500ml"), plages d'âge
     * ("6-12 mois"), codes ("5HMO"). Le chiffre doit être précédé d'un début/espace
     * et non suivi d'un autre chiffre, d'un tiret ou d'une unité.
     */
    private function hasAgeToken(string $name, string $digit): bool
    {
        return 1 === preg_match(
            '/(^|\s)' . $digit . '(?![\d\-])(?!\s*(?:g|kg|ml|l|mois|mo|m\b))(\s|$|er|e|ème|eme|\s*âge|\s*age)/u',
            $name
        );
    }

    /**
     * @param array<int, mixed> $categories
     * @param list<string> $tags
     */
    private function hasAnyTag(array $categories, array $tags): bool
    {
        foreach ($tags as $tag) {
            if (\in_array($tag, $categories, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $n
     *
     * @return list<array<string, mixed>>
     */
    private function buildBabyFoodNutrients(array $n): array
    {
        $result = [];

        // --- Sel : seuil ANSES 0,3 g/100 g (aligné sur la règle de scoring) ---
        if (is_numeric($n['salt_100g'] ?? null)) {
            $value = (float) $n['salt_100g'];
            $result[] = $this->buildGauge(
                'Sel',
                'g',
                $value,
                threshold: 0.3,
                maxScale: 1.0,
                belowMsg: 'Sous le seuil ANSES pour le nourrisson.',
                aboveMsg: 'Au-dessus du seuil ANSES pour le nourrisson.',
                reference: 'Seuil ANSES nourrisson : 0,3 g/100 g',
            );
        }

        // --- Sucres : le WHO NPPM (2022) raisonne en % de l'énergie, pas en g/100 g.
        // On affiche donc la valeur brute SANS seuil chiffré trompeur : le jugement
        // se fait via la règle de scoring (% d'énergie). Ici, information seule. ---
        if (is_numeric($n['sugars_100g'] ?? null)) {
            $value = (float) $n['sugars_100g'];
            $result[] = [
                'name' => 'Sucres',
                'category' => 'Nutrition',
                'available' => true,
                'value' => $value,
                'unit' => 'g',
                'threshold_baby' => null,
                'max_scale' => 40.0,
                'level' => 'info',
                'message' => 'La teneur en sucres est évaluée en pourcentage de l\'énergie (voir l\'onglet Détails), conformément au modèle OMS Europe pour les 6-36 mois.',
                'reference' => 'Référence : OMS Europe, Nutrient and Promotion Profile Model (2022)',
            ];
        }

        // --- Protéines : seuil ANSES exprimé en % de l'énergie (AET), PAS en g/100 g.
        // On calcule le % comme la règle de scoring, pour rester cohérent. ---
        $proteins = $n['proteins_100g'] ?? null;
        $energy = $n['energy-kcal_100g'] ?? null;
        if (is_numeric($proteins) && is_numeric($energy) && (float) $energy > 0) {
            $pctEnergy = ((float) $proteins * 4) / (float) $energy * 100;
            $result[] = $this->buildGauge(
                'Protéines (% énergie)',
                '%',
                round($pctEnergy, 1),
                threshold: 15.0,
                maxScale: 30.0,
                belowMsg: 'Sous le seuil ANSES de 15 % de l\'apport énergétique.',
                aboveMsg: 'Au-dessus du seuil ANSES de 15 % de l\'apport énergétique.',
                reference: 'Seuil ANSES 0-3 ans : 15 % de l\'apport énergétique total',
            );
        }

        // --- Calories : le WHO NPPM fixe un MINIMUM (60 kcal/100 g pour purées),
        // pas un maximum. On affiche la valeur en information, sans faux plafond. ---
        if (is_numeric($n['energy-kcal_100g'] ?? null)) {
            $value = (float) $n['energy-kcal_100g'];
            $result[] = [
                'name' => 'Calories',
                'category' => 'Nutrition',
                'available' => true,
                'value' => $value,
                'unit' => 'kcal',
                'threshold_baby' => null,
                'max_scale' => 500.0,
                'level' => 'info',
                'message' => 'Le modèle OMS Europe recommande un minimum de 60 kcal/100 g pour les purées de fruits/légumes et produits laitiers (densité énergétique suffisante).',
                'reference' => 'Référence : OMS Europe NPPM (2022), seuil minimal 60 kcal/100 g',
            ];
        }

        return $result;
    }

    /**
     * Construit une jauge à seuil unique (sous le seuil = ok, au-dessus = alerte).
     *
     * @return array<string, mixed>
     */
    private function buildGauge(
        string $name,
        string $unit,
        float $value,
        float $threshold,
        float $maxScale,
        string $belowMsg,
        string $aboveMsg,
        string $reference,
    ): array {
        return [
            'name' => $name,
            'category' => 'Nutrition',
            'available' => true,
            'value' => $value,
            'unit' => $unit,
            'threshold_baby' => $threshold,
            'max_scale' => $maxScale,
            'level' => $value <= $threshold ? 'good' : 'limit',
            'message' => $value <= $threshold ? $belowMsg : $aboveMsg,
            'reference' => $reference,
        ];
    }
}
