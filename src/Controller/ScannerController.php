<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;
use App\Service\Product\ProductPreviewBuilder;
use App\Service\Scanner\ScanProductHandler;
use App\Service\Scoring\BabyProductDetectorInterface;
use App\Service\Session\ScanSessionCookieManager;
use App\Service\Session\ScanSessionManager;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du scanner de code-barres.
 */
final class ScannerController extends AbstractController
{
    public function __construct(
        private readonly BabyProductDetectorInterface $babyProductDetector,
        private readonly ScanSessionManager $scanSessionManager,
        private readonly ScanProductHandler $scanProductHandler,
        private readonly ProductPreviewBuilder $productPreviewBuilder,
        private readonly ScanSessionCookieManager $scanSessionCookieManager,
        private readonly RateLimiterFactory $scanLimiter,
    ) {
    }

    #[Route('/app/scanner', name: 'app_pwa_scanner', methods: ['GET'])]
    public function scanner(): Response
    {
        return $this->render('pages/app/scanner.html.twig');
    }

    #[Route('/app/saisie-manuelle', name: 'app_pwa_manual_entry', methods: ['GET'])]
    public function manualEntry(): Response
    {
        return $this->render('pages/app/manual_entry.html.twig');
    }

    #[Route('/app/scan/{ean}', name: 'app_pwa_scan', methods: ['GET'], requirements: ['ean' => '\d{13}'])]
    public function scan(string $ean, Request $request): Response
    {
        $limiter = $this->scanLimiter->create($request->getClientIp() ?? 'anonymous');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Trop de scans, réessaie dans un instant.');
        }
        try {
            $product = $this->scanProductHandler->findOrFetchProduct($ean);
        } catch (InvalidArgumentException) {
            return $this->render('pages/app/scan_error.html.twig', [
                'errorTitle' => 'Code-barres invalide',
                'errorMessage' => 'Ce code-barres n\'est pas valide.',
            ], new Response('', Response::HTTP_NOT_FOUND));
        } catch (ProductNotFoundException) {
            return $this->render('pages/app/scan_error.html.twig', [
                'errorTitle' => 'Produit inconnu',
                'errorMessage' => 'Ce produit n\'existe pas dans la base Open Food Facts.',
            ], new Response('', Response::HTTP_NOT_FOUND));
        } catch (OpenFoodFactsUnavailableException) {
            return $this->render('pages/app/scan_error.html.twig', [
                'errorTitle' => 'Service indisponible',
                'errorMessage' => 'Open Food Facts est temporairement inaccessible.',
            ], new Response('', Response::HTTP_SERVICE_UNAVAILABLE));
        }

        $scanSession = $this->scanSessionManager->resolveScanSession($request);

        if (!$this->babyProductDetector->isBabyProduct($product)) {
            return $this->render('pages/app/scan_error.html.twig', [
                'errorTitle' => 'Produit non destiné aux 0-3 ans',
                'errorMessage' => 'NutriPetit analyse uniquement les produits alimentaires destinés aux nourrissons et jeunes enfants (0-3 ans). 
                Pour les autres produits, nous vous invitons à utiliser une application généraliste.',
            ], new Response('', Response::HTTP_OK));
        }

        $scanData = $this->scanProductHandler->processScan($product, $request, $scanSession);

        $viewData = $this->productPreviewBuilder->build(
            $product,
            $scanData,
        );

        $response = $this->render('pages/app/product_preview.html.twig', $viewData);
        $this->scanSessionCookieManager
            ->ensureScanSessionCookie(
                $request,
                $response,
                $scanSession
            );

        return $response;
    }
}
