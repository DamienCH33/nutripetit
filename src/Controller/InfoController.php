<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page d'informations sur le scoring.
 *
 * Page transparence : explique le scoring nutritionnel, les règles,
 * les sources officielles et la différence avec Nutri-Score adulte.
 *
 * En V1, les règles sont passées en dur ici. En V2 elles viendront
 * directement du repository ScoringRule (référentiel DB).
 */
final class InfoController extends AbstractController
{
    #[Route('/app/infos', name: 'app_pwa_info', methods: ['GET'])]
    public function index(): Response
    {
        // Échelle de score (cohérente avec ScoreCalculator V2)
        $scoreScale = [
            ['min' => 80, 'max' => 100, 'level' => 'excellent', 'label' => 'Excellent', 'description' => 'Adapté à votre bébé'],
            ['min' => 60, 'max' => 79, 'level' => 'good', 'label' => 'Bon', 'description' => 'Acceptable, sans plus'],
            ['min' => 40, 'max' => 59, 'level' => 'medium', 'label' => 'Moyen', 'description' => 'À limiter ou occasionnel'],
            ['min' => 0, 'max' => 39, 'level' => 'bad', 'label' => 'À éviter', 'description' => 'Non recommandé pour bébé'],
        ];

        // Les 8 règles de scoring V1
        $rules = [
            [
                'code' => 'added_sugars',
                'label' => 'Sucres ajoutés',
                'description' => 'Présence de sucres ajoutés (saccharose, sirop de glucose, dextrose…)',
                'impact' => -25,
                'ageRange' => '0-36 mois',
                'sourceName' => 'OMS - Guideline Sugars Intake (2015)',
                'sourceUrl' => 'https://www.who.int/publications/i/item/9789241549028',
            ],
            [
                'code' => 'added_salt',
                'label' => 'Sel ajouté',
                'description' => 'Plus de 0.3g de sel pour 100g de produit',
                'impact' => -20,
                'ageRange' => '0-12 mois',
                'sourceName' => 'ANSES - Repères nutritionnels PNNS',
                'sourceUrl' => 'https://www.anses.fr',
            ],
            [
                'code' => 'sweeteners',
                'label' => 'Édulcorants',
                'description' => 'Aspartame, sucralose, stévia et autres édulcorants déconseillés aux jeunes enfants',
                'impact' => -30,
                'ageRange' => '0-36 mois',
                'sourceName' => 'EFSA + ANSES',
                'sourceUrl' => 'https://www.efsa.europa.eu',
            ],
            [
                'code' => 'artificial_flavors',
                'label' => 'Arômes artificiels',
                'description' => 'Présence d\'arômes de synthèse non recommandés',
                'impact' => -10,
                'ageRange' => '0-36 mois',
                'sourceName' => 'PNNS',
                'sourceUrl' => 'https://www.mangerbouger.fr',
            ],
            [
                'code' => 'palm_oil',
                'label' => 'Huile de palme',
                'description' => 'Riche en acides gras saturés, à limiter chez le jeune enfant',
                'impact' => -5,
                'ageRange' => '0-36 mois',
                'sourceName' => 'PNNS + ANSES',
                'sourceUrl' => 'https://www.anses.fr',
            ],
            [
                'code' => 'excessive_protein',
                'label' => 'Densité protéique excessive',
                'description' => 'Dépassement du seuil OMS pour l\'apport protéique des nourrissons',
                'impact' => -10,
                'ageRange' => '0-12 mois',
                'sourceName' => 'OMS - Complementary Feeding',
                'sourceUrl' => 'https://www.who.int',
            ],
            [
                'code' => 'major_allergens',
                'label' => 'Allergènes majeurs',
                'description' => 'Présence d\'allergènes (gluten, arachide, œuf, lait…) mentionnée à titre informatif',
                'impact' => 0,
                'ageRange' => 'Tous',
                'sourceName' => 'Règlement INCO 1169/2011',
                'sourceUrl' => 'https://eur-lex.europa.eu',
            ],
            [
                'code' => 'organic_certified',
                'label' => 'Certification Bio AB',
                'description' => 'Bonus accordé aux produits certifiés issus de l\'agriculture biologique',
                'impact' => 5,
                'ageRange' => 'Tous',
                'sourceName' => 'Agence Bio - Label AB',
                'sourceUrl' => 'https://www.agencebio.org',
            ],
        ];

        // Sources officielles utilisées
        $sources = [
            ['name' => 'ANSES', 'description' => 'Agence française de sécurité sanitaire', 'url' => 'https://www.anses.fr'],
            ['name' => 'OMS', 'description' => 'Organisation Mondiale de la Santé', 'url' => 'https://www.who.int'],
            ['name' => 'EFSA', 'description' => 'Autorité européenne de sécurité des aliments', 'url' => 'https://www.efsa.europa.eu'],
            ['name' => 'PNNS', 'description' => 'Programme national nutrition santé', 'url' => 'https://www.mangerbouger.fr'],
        ];

        return $this->render('pages/app/info.html.twig', [
            'scoreScale' => $scoreScale,
            'rules' => $rules,
            'sources' => $sources,
            'algoVersion' => '1.0.0',
        ]);
    }
}
