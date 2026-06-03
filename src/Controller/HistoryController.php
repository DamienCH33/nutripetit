<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de l'historique des scans.
 */
final class HistoryController extends AbstractController
{
    #[Route('/app/historique', name: 'app_pwa_history', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/app/history.html.twig');
    }
}
