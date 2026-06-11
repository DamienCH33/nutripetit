<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\Evaluator\ExcessiveProteinsEvaluator;
use PHPUnit\Framework\TestCase;

final class ExcessiveProteinsEvaluatorTest extends TestCase
{
    private ExcessiveProteinsEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExcessiveProteinsEvaluator();
    }

    public function testSupportsOnlyItsRule(): void
    {
        self::assertTrue($this->evaluator->supports($this->rule('excessive_proteins')));
        self::assertFalse($this->evaluator->supports($this->rule('iron_rich')));
    }

    public function testTriggersAboveAetThreshold(): void
    {
        // 10g prot × 4 kcal / 100 kcal = 40% AET > 15%.
        $product = new Product('3000000000230', 'Plat viande')
            ->setNutriments(['proteins_100g' => 10, 'energy-kcal_100g' => 100]);

        $applied = $this->evaluator->evaluate($product, $this->rule('excessive_proteins', -15), null);

        self::assertInstanceOf(AppliedRuleDto::class, $applied);
        self::assertStringContainsString('40', $applied->reason);
    }

    public function testDoesNotTriggerAtThreshold(): void
    {
        // 3.75g × 4 / 100 = 15% : le code exige strictement > seuil.
        $product = new Product('3000000000231', 'Plat')
            ->setNutriments(['proteins_100g' => 3.75, 'energy-kcal_100g' => 100]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('excessive_proteins'), null));
    }

    public function testHandlesStringValuesFromOff(): void
    {
        // OFF renvoie souvent des chaînes : is_numeric doit les accepter.
        $product = new Product('3000000000232', 'Plat')
            ->setNutriments(['proteins_100g' => '10', 'energy-kcal_100g' => '100']);

        self::assertNotNull($this->evaluator->evaluate($product, $this->rule('excessive_proteins'), null));
    }

    public function testDoesNotCrashOnZeroEnergy(): void
    {
        // Division par zéro évitée.
        $product = new Product('3000000000233', 'Eau')
            ->setNutriments(['proteins_100g' => 5, 'energy-kcal_100g' => 0]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('excessive_proteins'), null));
    }

    public function testDoesNotTriggerWhenDataMissing(): void
    {
        $product = new Product('3000000000234', 'Produit')
            ->setNutriments(['proteins_100g' => 10]);

        self::assertNull($this->evaluator->evaluate($product, $this->rule('excessive_proteins'), null));
    }

    private function rule(string $code, int $points = -15): ScoringRule
    {
        return new ScoringRule($code, 'Excès de protéines', '', '1.0.0', $points, 'ANSES', 'https://example.test');
    }
}
