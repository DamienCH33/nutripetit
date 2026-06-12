<?php

declare(strict_types=1);

namespace App\Scoring;

final class ScoringRulesProvider
{
    public const ALGO_VERSION = '1.0.0';

    /**
     * @return list<array<string, mixed>>
     */
    public static function getRules(): array
    {
        return [
            //
            // MALUS - Substances/contenus à éviter
            //
            [
                'code' => 'added_sugars',
                'label' => 'Sucres ajoutés',
                'description' => 'Présence de sucres ajoutés (saccharose, sirop de glucose, dextrose, fructose, maltose, miel). L\'OMS recommande zéro sucre ajouté chez le nourrisson.',
                'pointsImpact' => -30,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'OMS Guideline: Sugars intake (2015) + ANSES 2019',
                'sourceUrl' => 'https://www.who.int/publications/i/item/9789241549028',
            ],
            [
                'code' => 'sweeteners',
                'label' => 'Édulcorants',
                'description' => 'Présence d\'édulcorants (aspartame, sucralose, stévia, acésulfame K, saccharine). L\'ANSES recommande de proscrire ces ingrédients chez les jeunes enfants.',
                'pointsImpact' => -40,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES Avis 0-3 ans (2019) + EFSA',
                'sourceUrl' => 'https://www.anses.fr/fr/system/files/NUT2017SA0145.pdf',
            ],
            [
                'code' => 'added_salt',
                'label' => 'Sel ajouté excessif',
                'description' => 'Teneur en sel supérieure à 0.3g/100g chez le nourrisson de moins de 1 an. Le sel ajouté est déconseillé avant 12 mois.',
                'pointsImpact' => -25,
                'thresholdValue' => 0.3,
                'thresholdUnit' => 'g/100g',
                'ageMinMonths' => 0,
                'ageMaxMonths' => 12,
                'sourceName' => 'ANSES Avis 0-3 ans (2019) - Repères PNNS 4',
                'sourceUrl' => 'https://www.anses.fr/fr/system/files/NUT2017SA0145.pdf',
            ],
            [
                'code' => 'excessive_proteins',
                'label' => 'Protéines excessives',
                'description' => 'Apport protéique supérieur à 15% de l\'apport énergétique total. Les protéines excessives chez le jeune enfant favorisent le risque de surpoids ultérieur.',
                'pointsImpact' => -15,
                'thresholdValue' => 15.0,
                'thresholdUnit' => '%AET',
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES Référentiels nutritionnels 0-3 ans (2019)',
                'sourceUrl' => 'https://www.anses.fr/fr/content/nutrition-des-enfants-des-personnes-agees-et-des-femmes-enceintes-ou-allaitantes-lanses',
            ],
            [
                'code' => 'artificial_flavors',
                'label' => 'Arômes artificiels',
                'description' => 'Présence d\'arômes artificiels ou de synthèse, déconseillés par le PNNS 4 pour les jeunes enfants.',
                'pointsImpact' => -10,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'PNNS 4 / Santé publique France (2021)',
                'sourceUrl' => 'https://www.mangerbouger.fr/ressources-pros/elaboration-des-recommandations-nutritionnelles/les-recommandations-sur-la-diversification-alimentaire-des-enfants-jusqu-a-3-ans',
            ],
            [
                'code' => 'controversial_additives',
                'label' => 'Additifs controversés',
                'description' => 'Présence d\'additifs controversés : colorants azoïques (E102, E104, E110, E122, E124, E129), dioxyde de titane nanoparticulaire (E171). Évalués comme préoccupants par l\'EFSA et l\'ANSES.',
                'pointsImpact' => -15,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'Règlement EU 1333/2008 + ANSES',
                'sourceUrl' => 'https://www.anses.fr/fr/content/additifs-alimentaires',
            ],
            [
                'code' => 'palm_oil',
                'label' => 'Huile de palme',
                'description' => 'Présence d\'huile de palme. Riche en acides gras saturés, à limiter chez le jeune enfant.',
                'pointsImpact' => -5,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'PNNS 4 + ANSES',
                'sourceUrl' => 'https://www.mangerbouger.fr/',
            ],
            [
                'code' => 'soy_products',
                'label' => 'Produits à base de soja',
                'description' => 'Présence de soja transformé (tofu, yaourts au soja, boissons végétales). L\'ANSES indique que ces aliments ne sont pas adaptés aux enfants de moins de 3 ans.',
                'pointsImpact' => -15,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES Avis 0-3 ans (2019)',
                'sourceUrl' => 'https://www.anses.fr/fr/system/files/NUT2017SA0145.pdf',
            ],

            //
            // ALERTES CRITIQUES (impact score important)
            //
            [
                'code' => 'choking_hazard',
                'label' => 'Risque d\'étouffement',
                'description' => 'Aliments présentant un risque d\'étouffement : cacahuètes entières, fruits à coque entiers, raisins entiers, fruits à coque, raisins entiers. À ne pas proposer entiers avant 3 ans.',
                'pointsImpact' => -30,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES Avis 0-3 ans (2019)',
                'sourceUrl' => 'https://www.anses.fr/fr/system/files/NUT2017SA0145.pdf',
            ],
            [
                'code' => 'contaminated_fish',
                'label' => 'Poissons à risque contaminants',
                'description' => 'Poissons à risque PCB ou mercure : anguille, barbeau, brème, carpe, silure, thon, espadon, requin. À éviter chez les jeunes enfants.',
                'pointsImpact' => -25,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES recommandations poissons (2019)',
                'sourceUrl' => 'https://www.anses.fr/fr/content/consommation-de-poissons-pcb-et-methylmercure',
            ],

            //
            // BONUS - Garanties qualité
            //
            [
                'code' => 'high_fruit_vegetable',
                'label' => 'Riche en fruits et légumes',
                'description' => 'Composé d\'au moins 50% de fruits et légumes selon l\'analyse Open Food Facts. Favorise l\'apport en vitamines, minéraux et fibres.',
                'pointsImpact' => 5,
                'ageMinMonths' => 6,
                'ageMaxMonths' => 36,
                'sourceName' => 'PNNS 4 / ANSES',
                'sourceUrl' => 'https://www.mangerbouger.fr/',
            ],

            [
                'code' => 'baby_food_certified',
                'label' => 'Adapté aux nourrissons',
                'description' => 'Produit conforme à la Directive 2006/125/CE sur les aliments pour bébés. Composition réglementée et adaptée aux nourrissons.',
                'pointsImpact' => 10,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'Directive EU 2006/125/CE',
                'sourceUrl' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32006L0125',
            ],
            [
                'code' => 'organic_certified',
                'label' => 'Certification Bio AB',
                'description' => 'Produit certifié issu de l\'agriculture biologique (Label AB, Règlement EU 2018/848). Garantit l\'absence de pesticides de synthèse et d\'OGM.',
                'pointsImpact' => 8,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'Règlement EU 2018/848 - Agence Bio',
                'sourceUrl' => 'https://www.agencebio.org/',
            ],
            [
                'code' => 'iron_rich',
                'label' => 'Riche en fer biodisponible',
                'description' => 'Apport en fer suffisant pour couvrir les besoins du nourrisson. Le fer est crucial pour le développement cognitif entre 6 et 36 mois.',
                'pointsImpact' => 5,
                'ageMinMonths' => 6,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES Référentiels nutritionnels (2019)',
                'sourceUrl' => 'https://www.anses.fr/fr/content/les-r%C3%A9f%C3%A9rences-nutritionnelles-en-vitamines-et-min%C3%A9raux',
            ],
            [
                'code' => 'omega3_rich',
                'label' => 'Riche en oméga-3 (DHA/EPA)',
                'description' => 'Apport en acides gras polyinsaturés à longue chaîne (DHA, EPA). Essentiels au développement cérébral et visuel du jeune enfant.',
                'pointsImpact' => 5,
                'ageMinMonths' => 6,
                'ageMaxMonths' => 36,
                'sourceName' => 'ANSES AGPI-LC (2019)',
                'sourceUrl' => 'https://www.anses.fr/fr/content/les-acides-gras-de-la-famille-om%C3%A9ga-3-et-syst%C3%A8me-cardiovasculaire',
            ],

            //
            // INFORMATIF (alertes sans impact score)
            //
            [
                'code' => 'major_allergens',
                'label' => 'Allergènes majeurs',
                'description' => 'Présence d\'allergènes majeurs...',
                'pointsImpact' => 0,
                'ageMinMonths' => 0,
                'ageMaxMonths' => 36,
                'sourceName' => 'Règlement EU 1169/2011 (INCO)',
                'sourceUrl' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32011R1169',
            ],
        ];
    }
}
