<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ScoreResultRepository;
use App\Service\Session\ScanSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HistoryController extends AbstractController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ScanSessionManager $scanSessionManager,
        private readonly ScoreResultRepository $scoreResultRepository,
    ) {
    }

    #[Route('/app/historique', name: 'app_pwa_history', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $results = [];
        $total = 0;

        $session = $this->scanSessionManager->getSessionFromRequest($request);
        if (null !== $session) {
            $total = $this->scoreResultRepository->countBySession($session);
            $results = $this->scoreResultRepository->findRecentBySession(
                $session,
                self::PER_PAGE,
                ($page - 1) * self::PER_PAGE,
            );
        }

        return $this->render('pages/app/history.html.twig', [
            'results' => $results,
            'page' => $page,
            'lastPage' => (int) max(1, ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }
}
