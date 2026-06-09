<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;
use App\Entity\ScoreResult;

final class ProductPreviewBuilder
{
    public function __construct(
        private readonly CriticalAlertDetector $criticalAlertDetector,
        private readonly RuleSourceAggregator $ruleSourceAggregator,
        private readonly AdditiveExtractor $additiveExtractor,
        private readonly EnvironmentAnalyzer $environmentAnalyzer,
        private readonly AgeScoreSimulator $ageScoreSimulator,
        private readonly NutrientViewBuilder $nutrientViewBuilder,
        private readonly MinimumAgeExtractor $minimumAgeExtractor,
        private readonly CarbonFootprintExtractor $carbonFootprintExtractor,
    ) {}

    /**
     * Construit les données de vue de la page produit.
     *
     * @param array{babyAgeMonths: int|null, isInfantFormula: bool, scoreResult: ScoreResult} $scanData
     *
     * @return array<string, mixed>
     */
    public function build(Product $product, array $scanData): array
    {
        $babyAgeMonths   = $scanData['babyAgeMonths'];
        $isInfantFormula = $scanData['isInfantFormula'];
        $scoreResult     = $scanData['scoreResult'];

        $nutrients     = $this->nutrientViewBuilder->buildNutrients($product, $isInfantFormula);
        $uniqueSources = $this->ruleSourceAggregator->aggregate($scoreResult->getAppliedRules());
        $criticalAlert = $this->criticalAlertDetector->detect($scoreResult->getAppliedRules());
        $minAgeMonths  = $this->minimumAgeExtractor->extractMinAgeMonths($product);
        $environment   = $this->environmentAnalyzer->buildEnvironment($product);
        $scoresByAge   = $this->ageScoreSimulator->buildScoresByAge($product, $isInfantFormula, $babyAgeMonths, $minAgeMonths);

        return [
            'product' => [
                'ean'       => $product->getEan(),
                'name'      => $product->getName(),
                'brand'     => $product->getBrand(),
                'image_url' => $product->getImageUrl(),
            ],
            'scoresByAge'     => $scoresByAge,
            'criticalAlert'   => $criticalAlert,
            'nutrients'       => $nutrients,
            'environment'     => $environment,
            'uniqueSources'   => $uniqueSources,
            'minAgeMonths'    => $minAgeMonths,
            'additives'       => $this->additiveExtractor->extractAdditives($product),
            'carbonFootprint' => $this->carbonFootprintExtractor->extractCarbonFootprint($product),
        ];
    }
}
