<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller temporaire pour visualiser la maquette de la page produit
 * avec des données fictives, avant branchement sur le ScoreCalculator réel.
 *
 * À supprimer une fois ScannerController::scan() implémenté.
 */
final class ProductPreviewController extends AbstractController
{
    #[Route('/app/preview/produit', name: 'app_pwa_product_preview', methods: ['GET'])]
    public function index(): Response
    {
        $product = [
            'name' => 'Petits boudoirs orange douce',
            'brand' => 'Babybio',
            'ean' => '3288131520063',
            'image_url' => null,
        ];

        $babyAgeMonths = 8;
        $finalScore = 72;
        $level = 'good';
        $algoVersion = '1.0.0';

        $scoresByAge = [
            ['months' => 6, 'score' => 52, 'level' => 'occasional', 'label' => 'Occasionnel'],
            ['months' => 8, 'score' => 72, 'level' => 'good', 'label' => 'Bon choix'],
            ['months' => 12, 'score' => 78, 'level' => 'good', 'label' => 'Bon choix'],
            ['months' => 24, 'score' => 82, 'level' => 'good', 'label' => 'Bon choix'],
        ];

        $criticalAlert = null;

        $appliedRules = [
            [
                'rule_code' => 'organic_certified',
                'rule_label' => 'Certification Bio AB',
                'points' => 8,
                'reason' => 'Label AB certifié détecté.',
                'source_name' => 'Règlement EU 2018/848',
                'source_url' => 'https://www.agencebio.org/',
                'category' => 'bonus',
                'icon' => 'lucide:leaf',
            ],
            [
                'rule_code' => 'baby_food_certified',
                'rule_label' => 'Adapté aux nourrissons',
                'points' => 10,
                'reason' => 'Conforme à la Directive 2006/125/CE.',
                'source_name' => 'Directive EU 2006/125/CE',
                'source_url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32006L0125',
                'category' => 'bonus',
                'icon' => 'lucide:baby',
            ],
            [
                'rule_code' => 'added_sugars',
                'rule_label' => 'Sucres ajoutés',
                'points' => -30,
                'reason' => 'Présence détectée : sirop de glucose, saccharose.',
                'source_name' => 'OMS Guideline Sugars (2015)',
                'source_url' => 'https://www.who.int/publications/i/item/9789241549028',
                'category' => 'malus',
                'icon' => 'lucide:candy',
            ],
            [
                'rule_code' => 'palm_oil',
                'rule_label' => 'Huile de palme',
                'points' => -5,
                'reason' => 'Huile de palme détectée dans la liste d\'ingrédients.',
                'source_name' => 'PNNS 4 / Santé publique France',
                'source_url' => 'https://www.mangerbouger.fr/',
                'category' => 'malus',
                'icon' => 'lucide:trees',
            ],
        ];

        $nutrients = [
            [
                'name' => 'Sucres',
                'value' => 30.0,
                'unit' => 'g',
                'threshold_baby' => 4.0,
                'max_scale' => 40.0,
                'level' => 'limit',
                'message' => 'Trop sucré pour les recommandations ANSES nourrisson.',
                'reference' => 'ANSES nourrisson : 4g/100g max',
            ],
            [
                'name' => 'Sel',
                'value' => 0.08,
                'unit' => 'g',
                'threshold_baby' => 0.3,
                'max_scale' => 1.0,
                'level' => 'ideal',
                'message' => 'Très faible teneur en sel, idéal pour bébé.',
                'reference' => 'ANSES nourrisson : 0.3g/100g max',
            ],
            [
                'name' => 'Protéines',
                'value' => 5.8,
                'unit' => 'g',
                'threshold_baby' => 15.0,
                'max_scale' => 25.0,
                'level' => 'good',
                'message' => 'Apport en protéines équilibré.',
                'reference' => 'ANSES nourrisson : max 15% AET',
            ],
            [
                'name' => 'Calories',
                'value' => 459.0,
                'unit' => 'kcal',
                'threshold_baby' => 400.0,
                'max_scale' => 800.0,
                'level' => 'limit',
                'message' => 'Un peu trop calorique pour un produit destiné aux jeunes enfants.',
                'reference' => 'ANSES nourrisson : ~400 kcal/100g recommandé',
            ],
        ];

        // Section Environnement
        $environment = [
            'origin' => [
                'country' => 'France',
                'flag' => '🇫🇷',
            ],
            'bio_certified' => true,
            'palm_oil_free' => true,
            'packaging' => [
                'label' => 'Partiellement recyclable',
                'level' => 'limit',
                'details' => [
                    ['name' => 'Boîte carton', 'recyclable' => true],
                    ['name' => 'Sachets plastique individuels', 'recyclable' => false],
                ],
            ],
        ];

        $uniqueSources = [];
        foreach ($appliedRules as $rule) {
            $key = $rule['source_name'];
            if (!isset($uniqueSources[$key])) {
                $uniqueSources[$key] = [
                    'name' => $rule['source_name'],
                    'url' => $rule['source_url'],
                    'rules' => [],
                ];
            }
            $uniqueSources[$key]['rules'][] = $rule['rule_label'];
        }

        return $this->render('pages/app/product_preview.html.twig', [
            'product' => $product,
            'babyAgeMonths' => $babyAgeMonths,
            'finalScore' => $finalScore,
            'level' => $level,
            'algoVersion' => $algoVersion,
            'scoresByAge' => $scoresByAge,
            'criticalAlert' => $criticalAlert,
            'appliedRules' => $appliedRules,
            'nutrients' => $nutrients,
            'environment' => $environment,
            'uniqueSources' => array_values($uniqueSources),
        ]);
    }
}
