<?php

declare(strict_types=1);

namespace App\Enum;

use App\Enum\ScoringAlgorithm;

enum ScoreLevel: string
{
    case Ideal = 'ideal';
    case Good = 'good';
    case Occasional = 'occasional';
    case Limit = 'limit';
    case Discouraged = 'discouraged';

    /** Détermine le niveau à partir d'un score, selon l'algorithme. */
    public static function fromScore(int $score, ScoringAlgorithm $algorithm): self
    {
        return match ($algorithm) {
            ScoringAlgorithm::Food => match (true) {
                $score >= 85 => self::Ideal,
                $score >= 70 => self::Good,
                $score >= 50 => self::Occasional,
                $score >= 30 => self::Limit,
                default      => self::Discouraged,
            },
            // Laits infantiles : base conforme, jamais "déconseillé"
            ScoringAlgorithm::InfantFormula => match (true) {
                $score >= 95 => self::Ideal,
                $score >= 85 => self::Good,
                $score >= 70 => self::Occasional,
                default      => self::Limit,
            },
        };
    }

    /** Libellé affiché, selon l'algorithme. */
    public function label(ScoringAlgorithm $algorithm): string
    {
        return match ($algorithm) {
            ScoringAlgorithm::Food => match ($this) {
                self::Ideal       => 'Idéal pour bébé',
                self::Good        => 'Bon choix',
                self::Occasional  => 'Occasionnel',
                self::Limit       => 'À limiter',
                self::Discouraged => 'Déconseillé',
            },
            ScoringAlgorithm::InfantFormula => match ($this) {
                self::Ideal => 'Excellent pour bébé',
                self::Good  => 'Bon choix',
                default     => 'Conforme',
            },
        };
    }

    /** Bornes de l'échelle aliments (affichage page Infos). */
    public function min(): int
    {
        return match ($this) {
            self::Ideal => 85,
            self::Good => 70,
            self::Occasional => 50,
            self::Limit => 30,
            self::Discouraged => 0,
        };
    }

    public function max(): int
    {
        return match ($this) {
            self::Ideal => 100,
            self::Good => 84,
            self::Occasional => 69,
            self::Limit => 49,
            self::Discouraged => 29,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ideal       => 'Composition optimale, recommandé',
            self::Good        => 'Adapté à votre enfant',
            self::Occasional  => 'Acceptable de temps en temps',
            self::Limit       => 'À consommer rarement',
            self::Discouraged => 'Non recommandé pour bébé',
        };
    }
}
