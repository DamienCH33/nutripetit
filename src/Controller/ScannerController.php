<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du scanner de code-barres.
 */
final class ScannerController extends AbstractController
{
    #[Route('/app/scanner', name: 'app_pwa_scanner', methods: ['GET'])]
    public function scan(): Response
    {
        return $this->render('pages/app/scanner.html.twig');
    }

    #[Route('/app/saisie-manuelle', name: 'app_pwa_manual_entry', methods: ['GET'])]
    public function manualEntry(): Response
    {
        return $this->render('pages/app/manual_entry.html.twig');
    }
}
