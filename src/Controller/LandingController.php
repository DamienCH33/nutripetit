<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    /**
     * robots.txt généré dynamiquement : l'app (/app/) est privée, la landing
     * est indexable. URL du sitemap absolue quel que soit le domaine déployé.
     */
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemapUrl = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /app/',
            '',
            'Sitemap: ' . $sitemapUrl,
            '',
        ]);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $urls = [
            $this->generateUrl('app_landing', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('app_landing_about', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('app_landing_privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('app_landing_legal', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $response = $this->render('sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
