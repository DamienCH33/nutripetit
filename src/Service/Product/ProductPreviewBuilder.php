<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;
use App\Entity\ScoreResult;
use App\Enum\ScoreLevel;
use App\Enum\ScoringAlgorithm;

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
        private readonly DataCompletenessChecker $completenessChecker,
    ) {
    }

    /**
     * Construit les données de vue de la page produit.
     *
     * @param array{babyAgeMonths: int|null, isInfantFormula: bool, scoreResult: ScoreResult} $scanData
     *
     * @return array<string, mixed>
     */
    public function build(Product $product, array $scanData): array
    {
        $babyAgeMonths = $scanData['babyAgeMonths'];
        $isInfantFormula = $scanData['isInfantFormula'];
        $scoreResult = $scanData['scoreResult'];

        $nutrients = $this->nutrientViewBuilder->buildNutrients($product, $isInfantFormula);
        $uniqueSources = $this->ruleSourceAggregator->aggregate($scoreResult->getAppliedRules());
        $criticalAlert = $this->criticalAlertDetector->detect($scoreResult->getAppliedRules());
        $minAgeMonths = $this->minimumAgeExtractor->extractMinAgeMonths($product);
        $environment = $this->environmentAnalyzer->buildEnvironment($product);
        $scoresByAge = $this->ageScoreSimulator->buildScoresByAge($product, $isInfantFormula, $babyAgeMonths, $minAgeMonths);

        $appliedRules = [];
        foreach ($scoreResult->getAppliedRules() as $rule) {
            $status = $rule['status'] ?? 'triggered';
            // La catégorie suit le SIGNE des points, pas le simple fait d'être déclenchée.
            // Depuis l'algo 1.1.0, les badges qualité (bio, adapté nourrissons...) et les
            // alertes allergènes valent 0 point : ce ne sont ni des malus ni des bonus,
            // mais des informations. Sans ce cas, ils atterrissaient dans « Points à
            // surveiller » aux côtés des vrais défauts nutritionnels.
            if ('satisfied' === $status) {
                $rule['category'] = 'satisfied';
            } elseif ($rule['points'] > 0) {
                $rule['category'] = 'bonus';
            } elseif ($rule['points'] < 0) {
                $rule['category'] = 'malus';
            } else {
                $rule['category'] = 'info';
            }
            $appliedRules[] = $rule;
        }

        return [
            'product' => [
                'ean' => $product->getEan(),
                'name' => $product->getName(),
                'brand' => $product->getBrand(),
                'image_url' => $product->getImageUrl(),
            ],
            'babyAgeMonths' => $babyAgeMonths,
            'finalScore' => $scoreResult->getFinalScore(),
            'level' => $scoreResult->getLevel(),
            'levelLabel' => ScoreLevel::from($scoreResult->getLevel())
                ->label($isInfantFormula ? ScoringAlgorithm::InfantFormula : ScoringAlgorithm::Food),
            'algoVersion' => $scoreResult->getAlgoVersion(),
            'isInfantFormula' => $isInfantFormula,
            'scoresByAge' => $scoresByAge,
            'criticalAlert' => $criticalAlert,
            'appliedRules' => $appliedRules,
            'nutrients' => $nutrients,
            'environment' => $environment,
            'uniqueSources' => $uniqueSources,
            'minAgeMonths' => $minAgeMonths,
            'additives' => $this->additiveExtractor->extractAdditives($product),
            'carbonFootprint' => $this->carbonFootprintExtractor->extractCarbonFootprint($product),
            'dataIncomplete' => !$this->completenessChecker->hasSufficientData($product),
        ];
    }
}
