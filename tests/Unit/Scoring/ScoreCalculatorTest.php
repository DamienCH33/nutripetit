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
        self::assertSame('1.0.0', $result->algoVersion);
    }

    public function testImpactsAreSummedFromBase100(): void
    {
        $rules = [$this->rule('a', -20), $this->rule('b', -15)];

        $result = $this->calculator($rules)->calculate($this->product());

        self::assertSame(65, $result->finalScore); // 100 - 20 - 15
        self::assertSame('occasional', $result->level); // >= 50
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

    private function product(): Product
    {
        return new Product('3000000000000', 'Produit test');
    }

    private function rule(string $code, int $points, ?int $ageMin = null, ?int $ageMax = null): ScoringRule
    {
        $rule = new ScoringRule($code, $code, '', '1.0.0', $points, 'source', 'https://example.test');
        if (null !== $ageMin) {
            $rule->setAgeMinMonths($ageMin);
        }
        if (null !== $ageMax) {
            $rule->setAgeMaxMonths($ageMax);
        }

        return $rule;
    }
}
