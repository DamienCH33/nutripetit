<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ScoreLevel;
use App\Enum\ScoringAlgorithm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fige l'échelle de score : empêche un nouveau désalignement
 * entre le moteur et la page Infos (85/70/50/30).
 */
final class ScoreLevelTest extends TestCase
{
    /**
     * @return iterable<string, array{int, ScoreLevel}>
     */
    public static function foodScores(): iterable
    {
        yield 'haut Ideal' => [100, ScoreLevel::Ideal];
        yield 'borne Ideal' => [85, ScoreLevel::Ideal];
        yield 'borne Good' => [84, ScoreLevel::Good];
        yield 'Good' => [70, ScoreLevel::Good];
        yield 'borne Occasional' => [69, ScoreLevel::Occasional];
        yield 'Occasional' => [50, ScoreLevel::Occasional];
        yield 'borne Limit' => [49, ScoreLevel::Limit];
        yield 'Limit' => [30, ScoreLevel::Limit];
        yield 'borne Discouraged' => [29, ScoreLevel::Discouraged];
        yield 'zero' => [0, ScoreLevel::Discouraged];
    }

    #[DataProvider('foodScores')]
    public function testFromScoreFood(int $score, ScoreLevel $expected): void
    {
        self::assertSame($expected, ScoreLevel::fromScore($score, ScoringAlgorithm::Food));
    }

    public function testInfantFormulaNeverDiscouraged(): void
    {
        self::assertSame(ScoreLevel::Limit, ScoreLevel::fromScore(0, ScoringAlgorithm::InfantFormula));
        self::assertSame(ScoreLevel::Limit, ScoreLevel::fromScore(50, ScoringAlgorithm::InfantFormula));
        self::assertSame(ScoreLevel::Occasional, ScoreLevel::fromScore(70, ScoringAlgorithm::InfantFormula));
        self::assertSame(ScoreLevel::Good, ScoreLevel::fromScore(85, ScoringAlgorithm::InfantFormula));
        self::assertSame(ScoreLevel::Ideal, ScoreLevel::fromScore(95, ScoringAlgorithm::InfantFormula));
    }

    public function testFoodLabels(): void
    {
        self::assertSame('Idéal pour bébé', ScoreLevel::Ideal->label(ScoringAlgorithm::Food));
        self::assertSame('Déconseillé', ScoreLevel::Discouraged->label(ScoringAlgorithm::Food));
    }

    public function testInfantFormulaLabels(): void
    {
        self::assertSame('Excellent pour bébé', ScoreLevel::Ideal->label(ScoringAlgorithm::InfantFormula));
        self::assertSame('Conforme', ScoreLevel::Limit->label(ScoringAlgorithm::InfantFormula));
    }
}
