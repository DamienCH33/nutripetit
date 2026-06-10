<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;
use App\Service\Scoring\ScoreCalculator;

final class AgeScoreSimulator
{
    public function __construct(
        private readonly ScoreCalculator $scoreCalculator,
    ) {
    }

    /**
     * @return list<array{months: int, score: int, level: string, label: string}>
     */
    public function buildScoresByAge(
        Product $product,
        bool $isInfantFormula,
        ?int $currentAge,
        ?int $minAgeMonths = null,
    ): array {
        if ($isInfantFormula) {
            return [];
        }

        $ages = [6, 12, 18, 24, 36];

        if (null !== $minAgeMonths) {
            $ages = array_values(array_filter($ages, static fn ($a) => $a >= $minAgeMonths));
        }

        $result = [];
        foreach ($ages as $age) {
            $dto = $this->scoreCalculator->calculate($product, $age);
            $result[] = [
                'months' => $age,
                'score' => $dto->finalScore,
                'level' => $dto->level,
                'label' => match ($dto->level) {
                    'ideal' => 'Idéal',
                    'good' => 'Bon choix',
                    'occasional' => 'Occasionnel',
                    'limit' => 'À limiter',
                    'discouraged' => 'Déconseillé',
                    default => '',
                },
            ];
        }

        return $result;
    }
}
