<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ScoreResultRepository;
use App\Service\Session\ScanSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HistoryController extends AbstractController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ScanSessionManager $scanSessionManager,
        private readonly ScoreResultRepository $scoreResultRepository,
    ) {
    }

    #[Route('/api/history', name: 'api_history', methods: ['GET'])]
    public function historyIndex(Request $request): JsonResponse
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

        return $this->json([
            'results' => array_map(
                static fn ($result): array => [
                    'product' => [
                        'ean' => $result->getProduct()->getEan(),
                        'name' => $result->getProduct()->getName(),
                        'brand' => $result->getProduct()->getBrand(),
                        'imageUrl' => $result->getProduct()->getImageUrl(),
                    ],
                    'finalScore' => $result->getFinalScore(),
                    'level' => $result->getLevel(),
                    'calculatedAt' => $result->getCalculatedAt()->format('c'),
                ],
                $results,
            ),
            'total' => $total,
            'page' => $page,
            'lastPage' => (int) max(1, ceil($total / self::PER_PAGE)),
        ]);
    }
}
