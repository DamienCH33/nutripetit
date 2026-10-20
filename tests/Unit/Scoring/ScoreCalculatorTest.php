<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Repository\ScoringRuleRepository;
use App\Service\Scoring\RuleEvaluator;
use App\Service\Scoring\ScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Teste le moteur d'agrégation : base 100, somme des impacts, clamp 0-100,
 * filtrage par âge. C'est le cœur — si ça casse, tous les scores sont faux.
 */
final class ScoreCalculatorTest extends TestCase
{
    public function testNoRuleGivesPerfectScore(): void
    {
        $result = $this->calculator([])->calculate($this->product());

        self::assertSame(100, $result->finalScore);
        self::assertSame('ideal', $result->level);
        self::assertSame([], $result->appliedRules);
        self::assertSame('1.3.0', $result->algoVersion);
    }

    public function testImpactsAreSummedFromBase100(): void
    {
        $rules = [$this->rule('a', -20), $this->rule('b', -15)];

        $result = $this->calculator($rules)->calculate($this->product());

        self::assertSame(65, $result->finalScore); // 100 - 20 - 15
        self::assertSame('occasional', $result->level); // 65 = borne basse Occasional (algo 1.1.0)
        self::assertCount(2, $result->appliedRules);
    }

    public function testScoreIsClampedToZero(): void
    {
        $result = $this->calculator([$this->rule('a', -200)])->calculate($this->product());

        self::assertSame(0, $result->finalScore);
        self::assertSame('discouraged', $result->level);
    }

    public function testScoreIsClampedTo100(): void
    {
        $result = $this->calculator([$this->rule('bonus', 30)])->calculate($this->product());

        self::assertSame(100, $result->finalScore); // 130 plafonné
        self::assertSame('ideal', $result->level);
    }

    public function testRuleOutOfAgeRangeIsIgnored(): void
    {
        $rules = [$this->rule('only_6m_plus', -50, ageMin: 6)];

        // Bébé de 3 mois : la règle ne s'applique pas -> score intact.
        $resultYoung = $this->calculator($rules)->calculate($this->product(), 3);
        self::assertSame(100, $resultYoung->finalScore);

        // Bébé de 8 mois : la règle s'applique.
        $resultOld = $this->calculator($rules)->calculate($this->product(), 8);
        self::assertSame(50, $resultOld->finalScore);
    }

    public function testScoreIsCappedWithoutIngredientList(): void
    {
        $result = $this->calculator([])->calculate($this->product(null));

        self::assertSame(ScoreCalculator::SCORE_CAP_WITHOUT_INGREDIENTS, $result->finalScore);
        self::assertSame('occasional', $result->level);
        $cap = $result->appliedRules[array_key_last($result->appliedRules)];
        self::assertSame('incomplete_ingredients', $cap->ruleCode);
        self::assertSame(-16, $cap->pointsImpact);
    }

    public function testCapLeavesALowerScoreUntouchedButExplainsIt(): void
    {
        $result = $this->calculator([$this->rule('a', -40)])->calculate($this->product('  '));

        self::assertSame(60, $result->finalScore);
        $cap = $result->appliedRules[array_key_last($result->appliedRules)];
        self::assertSame('incomplete_ingredients', $cap->ruleCode);
        self::assertSame(0, $cap->pointsImpact);
    }

    public function testRuleFromBirthAppliesWhenAgeIsUnknown(): void
    {
        // Ex. sel ajouté : 0-12 mois. Âge inconnu -> prudence, la règle compte.
        $rules = [$this->rule('added_salt', -15, ageMin: 0, ageMax: 12), $this->rule('iron_rich', -5, ageMin: 6)];

        $result = $this->calculator($rules)->calculate($this->product(), null);

        self::assertSame(85, $result->finalScore);
        self::assertSame(['added_salt'], array_map(static fn ($r) => $r->ruleCode, $result->appliedRules));
    }

    /**
     * @param list<ScoringRule> $rules
     */
    private function calculator(array $rules): ScoreCalculator
    {
        $repo = $this->createStub(ScoringRuleRepository::class);
        $repo->method('findActiveByVersion')->willReturn($rules);

        // Evaluator qui déclenche toujours avec l'impact de la règle :
        // isole le moteur de la logique métier des evaluators.
        $evaluator = new class implements RuleEvaluator {
            public function supports(ScoringRule $rule): bool
            {
                return true;
            }

            public function evaluate(Product $product, ScoringRule $rule, ?int $babyAgeMonths): AppliedRuleDto
            {
                return new AppliedRuleDto(
                    $rule->getCode(),
                    $rule->getLabel(),
                    $rule->getPointsImpact(),
                    'reason',
                    'source',
                    'https://example.test',
                );
            }
        };

        return new ScoreCalculator($repo, [$evaluator]);
    }

    private function product(?string $ingredients = 'carottes, eau'): Product
    {
        return new Product('3000000000000', 'Produit test')->setIngredientsRaw($ingredients);
    }

    private function rule(string $code, int $points, ?int $ageMin = null, ?int $ageMax = null): ScoringRule
    {
        $rule = new ScoringRule($code, $code, '', '1.1.0', $points, 'source', 'https://example.test');
        if (null !== $ageMin) {
            $rule->setAgeMinMonths($ageMin);
        }
        if (null !== $ageMax) {
            $rule->setAgeMaxMonths($ageMax);
        }

        return $rule;
    }
}
