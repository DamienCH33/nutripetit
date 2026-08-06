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
     * (~×8 vs reconstitué) dans les champs "prepared", alors que les seuils réglementaires
     * sont en /100 ml reconstitué. Comparer les deux donne des affichages faux ("14
     * nutriments au-dessus du seuil légal"). On affiche donc ces valeurs de référence
     * reconstituées à la place, avec un disclaimer. Le lait 3ème âge = lait de croissance.
     *
     * @var array<string, array<string, float>>
     */
    private const CIQUAL_INFANT_REFERENCE = [
        'first_age' => [   // Lait 1er âge, prêt à consommer (Ciqual 19013)
            'energy' => 71.0,
            'proteins' => 1.2,
            'fat' => 3.8,
            'carbohydrates' => 8.0,
            'sugars' => 6.7,
            'sodium' => 24.0,
            'calcium' => 60.0,
            'iron' => 0.64,
        ],
        'second_age' => [  // Lait 2e âge, prêt à consommer (Ciqual 19014)
            'energy' => 65.0,
            'proteins' => 1.2,
            'fat' => 2.9,
            'carbohydrates' => 8.2,
            'sugars' => 5.6,
            'sodium' => 22.0,
            'calcium' => 71.0,
            'iron' => 0.92,
        ],
        'growing_up' => [  // Lait de croissance / 3ème âge, liquide (Ciqual 19012)
            'energy' => 61.0,
            'proteins' => 1.3,
            'fat' => 2.7,
            'carbohydrates' => 7.8,
            'sugars' => 6.0,
            'sodium' => 24.0,
            'calcium' => 76.0,
            'iron' => 1.27,
        ],
    ];

    /**
     * Libellés + unités + catégorie pour l'affichage, dans l'ordre.
     *
     * @var list<array{key: string, name: string, unit: string, category: string}>
     */
    private const CIQUAL_DISPLAY = [
        ['key' => 'energy',        'name' => 'Énergie',    'unit' => 'kcal', 'category' => 'Macronutriments'],
        ['key' => 'proteins',      'name' => 'Protéines',  'unit' => 'g',    'category' => 'Macronutriments'],
        ['key' => 'fat',           'name' => 'Lipides',    'unit' => 'g',    'category' => 'Macronutriments'],
        ['key' => 'carbohydrates', 'name' => 'Glucides',   'unit' => 'g',    'category' => 'Macronutriments'],
        ['key' => 'sugars',        'name' => 'dont sucres', 'unit' => 'g',    'category' => 'Macronutriments'],
        ['key' => 'sodium',        'name' => 'Sodium',     'unit' => 'mg',   'category' => 'Minéraux'],
        ['key' => 'calcium',       'name' => 'Calcium',    'unit' => 'mg',   'category' => 'Minéraux'],
        ['key' => 'iron',          'name' => 'Fer',        'unit' => 'mg',   'category' => 'Minéraux'],
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
     * @return list<array<string, mixed>>
     */
    public function buildNutrients(Product $product, bool $isInfantFormula = false): array
    {
        if ($isInfantFormula) {
            // On n'utilise PAS les nutriments OFF pour les laits (valeurs poudre non fiables),
            // mais une table de référence Ciqual reconstituée selon l'âge du lait.
            return $this->buildInfantFormulaReferenceNutrients($this->detectMilkType($product));
        }

        return $this->buildBabyFoodNutrients($product->getNutriments());
    }

    /**
     * Détermine l'ÂGE du lait (1er / 2e / croissance) pour choisir le profil Ciqual.
     *
     * On ne détecte QUE l'âge, pas la spécialité (AR, AC, HA, sans lactose, prématuré…) :
     * ces laits respectent le même règlement UE 2016/127 et ont un profil nutritionnel
     * global proche du lait de base de leur âge. Leur spécificité est signalée ailleurs
     * (nom du produit, règles de score), pas dans les valeurs de référence affichées.
     *
     * Tags OFF vérifiés contre la taxonomie réelle (taxonomies/food/categories.txt).
     * Découvertes du croisement : le tag croissance est en:growth-milks (pas
     * en:growing-up-milk) ; les follow-on "from 2/3 years" sont des laits de croissance.
     *
     * Ordre = du plus spécifique au plus générique :
     *  1. croissance / 3ème âge (dont follow-on 2/3 ans) — EN PREMIER ;
     *  2. 2e âge / suite ;
     *  3. 1er âge = défaut (couvre 1er âge, "Relais 1er âge", AR/HA/prématuré sans âge,
     *     et tout lait sans mention d'âge détectable — cas le plus courant).
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
        // Le nom du fabricant est plus fiable que les tags OFF, qui se contredisent
        // parfois (ex. Guigoz 1er âge tagué À LA FOIS en:infant-formulas ET
        // en:growth-milks). Une mention d'âge dans le nom tranche donc en premier.

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
        // On teste dans l'ordre croissance -> 2e -> (défaut 1er). Les tags peuvent
        // être contradictoires : ce secours ne s'applique que si le nom est muet.
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
     * ("6-12 mois"), codes ("5HMO"). On exige que le chiffre soit entouré d'espaces
     * (ou en début/fin) et NON collé à une lettre/chiffre ou suivi d'une unité.
     */
    private function hasAgeToken(string $name, string $digit): bool
    {
        // Le chiffre isolé, suivi éventuellement de "age"/"âge", mais PAS d'une unité
        // (g, ml, kg, mois) ni d'un autre chiffre (830, 6-12).
        // \b{digit}\b entouré d'espaces, et non suivi de g/ml/mois/-.
        return 1 === preg_match(
            '/(^|\s)' . $digit . '(?![\d\-])(?!\s*(?:g|kg|ml|l|mois|mo|m\b))(\s|$|er|e|ème|eme|\s*âge|\s*age)/u',
            $name
        );
    }

    /**
     * Cherche une mention d'âge pour le chiffre donné ("1", "2", "3") dans le nom,
     * sous ses formes courantes : "2e âge", "2ème age", "age 2", ou le chiffre isolé
     * en fin de nom (ex. "Gallia 2", "Modilac AR 2"). Le \b évite de matcher "12".
     */
    private function matchesAge(string $name, string $digit): bool
    {
        $patterns = [
            $digit . 'e age',
            $digit . 'e âge',
            $digit . 'eme age',
            $digit . 'eme âge',
            $digit . 'ème age',
            $digit . 'ème âge',
            $digit . 'er age',
            $digit . 'er âge',   // "1er âge"
            'age ' . $digit,
            'âge ' . $digit,
        ];
        foreach ($patterns as $p) {
            if (str_contains($name, $p)) {
                return true;
            }
        }

        // Chiffre isolé en fin de nom (mot entier), ex. "gallia 2", "guigoz 3".
        return 1 === preg_match('/\b' . $digit . '\b\s*$/', trim($name));
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
     * Construit l'affichage nutritionnel d'un lait à partir des valeurs de référence
     * Ciqual reconstituées (et non des valeurs OFF poudre, non fiables).
     *
     * Tous les nutriments sont en niveau 'info' + flag is_reference : ce sont des
     * valeurs moyennes de catégorie, pas un jugement sur le produit précis. Le score
     * du lait, lui, se fait sur les ingrédients (InfantFormulaScoreCalculator).
     *
     * @return list<array<string, mixed>>
     */
    private function buildInfantFormulaReferenceNutrients(string $milkType): array
    {
        $ref = self::CIQUAL_INFANT_REFERENCE[$milkType] ?? self::CIQUAL_INFANT_REFERENCE['first_age'];

        $result = [];
        foreach (self::CIQUAL_DISPLAY as $d) {
            $result[] = [
                'name' => $d['name'],
                'category' => $d['category'],
                'available' => true,
                'value' => $ref[$d['key']],
                'unit' => $d['unit'],
                'threshold_baby' => null,
                'max_scale' => null,
                'level' => 'info',
                'is_reference' => true,
                'message' => 'Valeur moyenne de référence pour un lait reconstitué.',
                'reference' => 'Source : table Ciqual 2025 (ANSES). Les valeurs de l\'emballage concernent la poudre non reconstituée.',
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
