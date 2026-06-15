<?php

declare(strict_types=1);

namespace App\Service\Scanner;

use App\Entity\Product;
use App\Entity\ScanSession;
use App\Entity\ScoreResult;
use App\Repository\ProductRepository;
use App\Repository\ScoreResultRepository;
use App\Service\Ean13Validator;
use App\Service\Exception\OpenFoodFactsUnavailableException;
use App\Service\Exception\ProductNotFoundException;
use App\Service\OpenFoodFactsClientInterface;
use App\Service\Product\ProductImporter;
use App\Service\Scoring\Evaluator\InfantFormulaDetector;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use App\Service\Scoring\ScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class ScanProductHandler
{
    public function __construct(
        private readonly Ean13Validator $eanValidator,
        private readonly ProductRepository $productRepository,
        private readonly OpenFoodFactsClientInterface $offClient,
        private readonly ProductImporter $productImporter,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly InfantFormulaDetector $infantFormulaDetector,
        private readonly InfantFormulaScoreCalculator $infantFormulaScoreCalculator,
        private readonly EntityManagerInterface $em,
        private readonly ScoreResultRepository $scoreResultRepository,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws ProductNotFoundException
     * @throws OpenFoodFactsUnavailableException
     */
    public function findOrFetchProduct(string $ean): Product
    {
        if (!$this->eanValidator->isValid($ean)) {
            throw new InvalidArgumentException('Code-barres invalide');
        }

        $product = $this->productRepository->findByEan($ean);

        if (null !== $product) {
            return $product;
        }

        $dto = $this->offClient->fetchByEan($ean);

        return $this->productImporter
            ->createProductFromDto($dto);
    }

    /**
     * Âge du bébé : query param (?age=N), miroir de localStorage np_baby_age_months côté scanner.
     * Donnée transitoire — utilisée pour le calcul, jamais persistée ailleurs que dans le snapshot ScoreResult.
     */
    private function resolveBabyAgeMonths(Request $request): ?int
    {
        if (!$request->query->has('age')) {
            return null; // âge inconnu : le moteur n'appliquera que les règles sans tranche d'âge
        }

        // Borne 0–36 mois : un ?age=999 trafiqué ne doit pas fausser le score.
        return max(0, min(36, $request->query->getInt('age')));
    }

    /**
     * @return array<string, mixed>
     */
    public function processScan(
        Product $product,
        Request $request,
        ScanSession $scanSession,
    ): array {
        // Âge du bébé
        $babyAgeMonths = $this->resolveBabyAgeMonths($request);

        // Calcul du score
        $isInfantFormula = $this->infantFormulaDetector->isInfantFormula($product);

        if ($isInfantFormula) {
            $scoreDto = $this->infantFormulaScoreCalculator->calculate($product, $babyAgeMonths);
        } else {
            $scoreDto = $this->scoreCalculator->calculate($product, $babyAgeMonths);
        }
        $scoreResult = $this->scoreResultRepository
            ->findForSessionAndProduct(
                $scanSession,
                $product,
            );

        $appliedRules = array_map(
            static function ($r): array {
                $base = $r->toArray();
                $base['category'] = $base['points'] >= 0 ? 'bonus' : 'malus';
                $base['icon'] = 'lucide:circle';

                return $base;
            },
            $scoreDto->appliedRules,
        );

        if (null !== $scoreResult) {
            $scoreResult->refresh(
                $scoreDto->finalScore,
                $scoreDto->level,
                $appliedRules,
                $scoreDto->babyAgeMonths,
            );
        } else {
            $scoreResult = new ScoreResult(
                product: $product,
                finalScore: $scoreDto->finalScore,
                level: $scoreDto->level,
                algoVersion: $scoreDto->algoVersion,
                babyAgeMonths: $scoreDto->babyAgeMonths,
                scanSession: $scanSession,
            );

            $scoreResult->setAppliedRules($appliedRules);

            $this->em->persist($scoreResult);
        }

        $scanSession->touch();

        $this->em->flush();

        return [
            'scoreResult' => $scoreResult,
            'isInfantFormula' => $isInfantFormula,
            'babyAgeMonths' => $babyAgeMonths,
        ];
    }
}
