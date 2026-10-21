<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Enum\RuleStatus;
use App\Service\Product\EnvironmentAnalyzer;
use App\Service\Product\OrganicLabelDetector;
use App\Service\Product\PalmOilDetector;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use App\Service\Scoring\Evaluator\PalmOilEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verrou anti-régression : le score (aliments et laits) et le badge de la fiche
 * doivent toujours être d'accord sur la présence d'huile de palme.
 * Historique : « huile de palmiste » pénalisée dans le score mais affichée
 * « sans huile de palme » sur la fiche ; « palmitate » pénalisé sur les laits.
 */
final class PalmOilConsistencyTest extends TestCase
{
    /**
     * @return array<string, array{string|null, bool|null}>
     */
    public static function ingredientsProvider(): array
    {
        return [
            'palmiste' => ['Farine, huile de palmiste', true],
            'palme entre parenthèses' => ['Huiles végétales (palme, colza)', true],
            'palmitate' => ['Lactose, palmitate de rétinyle', false],
            'sans palme' => ['Pommes', false],
            'pas d\'ingrédients' => [null, null],
        ];
    }

    #[DataProvider('ingredientsProvider')]
    public function testAllServicesAgreeOnPalmOil(?string $ingredients, ?bool $containsPalmOil): void
    {
        $detector = new PalmOilDetector();
        $product = new Product('3000000000410', 'Produit')->setIngredientsRaw($ingredients);

        // Badge de la fiche
        $env = new EnvironmentAnalyzer(new OrganicLabelDetector(), $detector)->buildEnvironment($product);
        self::assertSame(null === $containsPalmOil ? null : !$containsPalmOil, $env['palm_oil_free']);

        // Score aliments
        $rule = new ScoringRule('palm_oil', 'Huile de palme', '', '1.0.0', -10, 'EFSA', 'https://example.test');
        $applied = new PalmOilEvaluator($detector)->evaluate($product, $rule, null);
        self::assertSame(
            match ($containsPalmOil) {
                true => RuleStatus::Triggered,
                false => RuleStatus::Satisfied,
                null => null,
            },
            $applied?->status,
        );

        // Score laits
        $codes = array_map(
            static fn ($r) => $r->ruleCode,
            new InfantFormulaScoreCalculator(new OrganicLabelDetector(), $detector)->calculate($product)->appliedRules,
        );
        self::assertSame(true === $containsPalmOil, \in_array('formula_palm_oil_present', $codes, true) || \in_array('formula_palm_oil_main', $codes, true));
        self::assertSame(false === $containsPalmOil, \in_array('formula_no_palm_oil', $codes, true));
    }
}
