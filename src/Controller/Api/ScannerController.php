<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\AppliedRuleDto;
use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;
use App\Service\Product\DataCompletenessChecker;
use App\Service\Scanner\ScanProductHandler;
use App\Service\Scoring\BabyProductDetectorInterface;
use App\Service\Scoring\ScoreCalculator;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly ScanProductHandler $scanProductHandler,
        private readonly RateLimiterFactory $scanLimiter,
        private readonly DataCompletenessChecker $completenessChecker,
        private readonly ScoreCalculator $scoreCalculator,
    ) {
    }

    #[Route('/api/scan/{ean}', name: 'api_scan', methods: ['GET'], requirements: ['ean' => '\d{13}'])]
    public function scan(string $ean, Request $request): JsonResponse
    {
        $limiter = $this->scanLimiter->create($request->getClientIp() ?? 'anonymous');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Trop de scans, réessaie dans un instant.');
        }
        try {
            $product = $this->scanProductHandler->findOrFetchProduct($ean);
        } catch (InvalidArgumentException) {
            return $this->json(['error' => 'Code-barres invalide.'], Response::HTTP_BAD_REQUEST);
        } catch (ProductNotFoundException) {
            return $this->json(['error' => 'Produit non trouvé.'], Response::HTTP_NOT_FOUND);
        } catch (OpenFoodFactsUnavailableException) {
            return $this->json(['error' => 'OpenFoodFacts est indisponible.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$this->completenessChecker->hasSufficientData($product)) {
            return $this->json([
                'errorTitle' => 'Produit impossible à évaluer / données incomplètes',
                'errorMessage' => 'NutriPetit ne peut pas évaluer ce produit car les données sont incomplètes.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->babyProductDetector->isBabyProduct($product)) {
            return $this->json([
                'errorTitle' => 'Produit non destiné aux 0-3 ans',
                'errorMessage' => 'NutriPetit analyse uniquement les produits alimentaires destinés aux nourrissons et jeunes enfants (0-3 ans). Pour les autres produits, nous vous invitons à utiliser une application généraliste.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $score = $this->scoreCalculator->calculate($product);

        return $this->json([
            'product' => [
                'ean' => $product->getEan(),
                'name' => $product->getName(),
                'brand' => $product->getBrand(),
                'imageUrl' => $product->getImageUrl(),
            ],
            'score' => [
                'finalScore' => $score->finalScore,
                'level' => $score->level,
                'appliedRules' => array_map(
                    static fn (AppliedRuleDto $rule): array => $rule->toArray(),
                    $score->appliedRules,
                ),
                'algoVersion' => $score->algoVersion,
            ],
        ]);
    }
}
