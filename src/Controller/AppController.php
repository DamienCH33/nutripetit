<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur principal de l'interface PWA.
 *
 * Gère la page d'accueil de l'app installable.
 */
final class AppController extends AbstractController
{
    #[Route('/app', name: 'app_pwa_home', methods: ['GET'])]
    public function home(): Response
    {
        $recentScans = [
            [
                'brand' => 'Blédina',
                'name' => 'Petits Pots Carottes',
                'date' => 'Aujourd\'hui',
                'score' => 82,
                'level' => 'excellent',
            ],
            [
                'brand' => 'Good Goût',
                'name' => 'Pommes Bananes',
                'date' => 'Hier',
                'score' => 74,
                'level' => 'good',
            ],
            [
                'brand' => 'Nestlé',
                'name' => 'P\'tite Céréale Vanille',
                'date' => 'Il y a 2 jours',
                'score' => 58,
                'level' => 'medium',
            ],
        ];

        return $this->render('pages/app/home.html.twig', [
            'recentScans' => $recentScans,
        ]);
    }
}
