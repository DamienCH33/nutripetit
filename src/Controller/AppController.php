<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ScoreResultRepository;
use App\Service\Session\ScanSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur principal de l'interface PWA.
 *
 * Gère la page d'accueil de l'app installable.
 */
final class AppController extends AbstractController
{
    public function __construct(
        private readonly ScanSessionManager $scanSessionManager,
        private readonly ScoreResultRepository $scoreResultRepository,
    ) {}

    #[Route('/app', name: 'app_pwa_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        $recentScans = [];
        $session = $this->scanSessionManager->getSessionFromRequest($request);
        if (null !== $session) {
            $recentScans = $this->scoreResultRepository->findRecentBySession($session, 3);
        }

        return $this->render('pages/app/home.html.twig', [
            'recentScans' => $recentScans,
        ]);
    }
}
