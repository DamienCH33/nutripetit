<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du site vitrine (landing publique).
 */
final class LandingController extends AbstractController
{
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('pages/landing/home.html.twig');
    }

    #[Route('/a-propos', name: 'app_landing_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('pages/landing/about.html.twig');
    }

    #[Route('/confidentialite', name: 'app_landing_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('pages/landing/privacy.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_landing_legal', methods: ['GET'])]
    public function legal(): Response
    {
        return $this->render('pages/landing/legal.html.twig');
    }
}
