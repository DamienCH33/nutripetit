<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ScoringRuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page d'informations sur le scoring.
 * Affiche l'échelle 5 niveaux et les 4 sources officielles.
 */
final class InfoController extends AbstractController
{
    private const ALGO_VERSION = '1.0.0';

    public function __construct(
        private readonly ScoringRuleRepository $ruleRepository,
    ) {
    }

    #[Route('/app/infos', name: 'app_pwa_info', methods: ['GET'])]
    public function index(): Response
    {
        // Échelle 5 niveaux (inspirée du Nutri-Score officiel mais adaptée 0-3 ans)
        $scoreScale = [
            ['min' => 85, 'max' => 100, 'level' => 'ideal', 'label' => 'Idéal pour bébé', 'description' => 'Composition optimale, recommandé'],
            ['min' => 70, 'max' => 84, 'level' => 'good', 'label' => 'Bon choix', 'description' => 'Adapté à votre enfant'],
            ['min' => 50, 'max' => 69, 'level' => 'occasional', 'label' => 'Occasionnel', 'description' => 'Acceptable de temps en temps'],
            ['min' => 30, 'max' => 49, 'level' => 'limit', 'label' => 'À limiter', 'description' => 'À consommer rarement'],
            ['min' => 0, 'max' => 29, 'level' => 'discouraged', 'label' => 'Déconseillé', 'description' => 'Non recommandé pour bébé'],
        ];

        // Sources officielles (mises à jour 2026 confirmées)
        $sources = [
            ['name' => 'ANSES', 'description' => 'Agence française - Avis 0-3 ans (2019)', 'url' => 'https://www.anses.fr'],
            ['name' => 'PNNS 4', 'description' => 'Programme National Nutrition Santé (2021)', 'url' => 'https://www.mangerbouger.fr'],
            ['name' => 'OMS', 'description' => 'Organisation Mondiale de la Santé', 'url' => 'https://www.who.int'],
            ['name' => 'EFSA', 'description' => 'Autorité européenne de sécurité des aliments', 'url' => 'https://www.efsa.europa.eu'],
        ];

        // Chargement dynamique des règles depuis la DB
        $rules = $this->ruleRepository->findActiveByVersion(self::ALGO_VERSION);

        return $this->render('pages/app/info.html.twig', [
            'scoreScale' => $scoreScale,
            'rules' => $rules,
            'sources' => $sources,
            'algoVersion' => self::ALGO_VERSION,
        ]);
    }
}
