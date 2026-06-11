<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\ScoreResult;
use PHPUnit\Framework\TestCase;

final class ScoreResultTest extends TestCase
{
    public function testConstructorClampsScore(): void
    {
        self::assertSame(100, $this->makeResult(150)->getFinalScore());
        self::assertSame(0, $this->makeResult(-10)->getFinalScore());
    }

    public function testRefreshUpdatesStateAndCounters(): void
    {
        $result = $this->makeResult(80);
        $firstScannedAt = $result->getFirstScannedAt();

        $result->refresh(200, 'ideal', [['rule_code' => 'x']], 12);

        self::assertSame(100, $result->getFinalScore());
        self::assertSame('ideal', $result->getLevel());
        self::assertSame(12, $result->getBabyAgeMonths());
        self::assertSame(2, $result->getScanCount());
        self::assertSame($firstScannedAt, $result->getFirstScannedAt());
        self::assertGreaterThanOrEqual($firstScannedAt, $result->getLastScannedAt());
    }

    private function makeResult(int $score): ScoreResult
    {
        return new ScoreResult(
            product: new Product('3000000000310', 'Produit'),
            finalScore: $score,
            level: 'good',
            algoVersion: '1.0.0',
        );
    }
}
