<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Service\Product\EnvironmentAnalyzer;
use App\Service\Product\OrganicLabelDetector;
use App\Service\Product\PalmOilDetector;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Verrou anti-régression : un même label bio doit être reconnu de façon
 * cohérente par tous les services qui consomment OrganicLabelDetector.
 * Empêche la réapparition de la divergence historique (en:eu-organic vu
 * bio par un service et non-bio par un autre).
 */
final class OrganicConsistencyTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function organicTagProvider(): array
    {
        return [
            'en:organic' => ['en:organic'],
            'en:eu-organic' => ['en:eu-organic'],
            'fr:ab-agriculture-biologique' => ['fr:ab-agriculture-biologique'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('organicTagProvider')]
    public function testAllServicesAgreeOnOrganic(string $tag): void
    {
        $detector = new OrganicLabelDetector();
        $product = new Product('3000000000280', 'Lait bio')
            ->setOffRawData(['labels_tags' => [$tag]]);

        // Détecteur
        self::assertTrue($detector->isOrganic($product), "détecteur: {$tag}");

        // EnvironmentAnalyzer (badge fiche)
        $env = new EnvironmentAnalyzer($detector, new PalmOilDetector())->buildEnvironment($product);
        self::assertTrue($env['bio_certified'], "analyzer: {$tag}");

        // InfantFormulaScoreCalculator (bonus lait)
        $result = new InfantFormulaScoreCalculator($detector, new PalmOilDetector())->calculate($product);
        $codes = array_map(static fn ($r) => $r->ruleCode, $result->appliedRules);
        self::assertContains('formula_organic', $codes, "calculator: {$tag}");
    }
}
