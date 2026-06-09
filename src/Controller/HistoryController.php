<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ScanSessionRepository;
use App\Repository\ScoreResultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class HistoryController extends AbstractController
{
    private const SESSION_COOKIE_NAME = 'np_session';

    public function __construct(
        private readonly ScanSessionRepository $scanSessionRepository,
        private readonly ScoreResultRepository $scoreResultRepository,
    ) {
    }

    #[Route('/app/historique', name: 'app_pwa_history', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $cookieValue = $request->cookies->get(self::SESSION_COOKIE_NAME);
        $results = [];

        if (\is_string($cookieValue) && Uuid::isValid($cookieValue)) {
            $session = $this->scanSessionRepository->findById(Uuid::fromString($cookieValue));
            if (null !== $session) {
                $results = $this->scoreResultRepository->findRecentBySession($session, 50);
            }
        }

        return $this->render('pages/app/history.html.twig', [
            'results' => $results,
        ]);
    }
}
