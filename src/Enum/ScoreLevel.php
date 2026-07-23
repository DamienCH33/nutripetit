<?php

declare(strict_types=1);

namespace App\Enum;

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
            // Seuils recalibrés en algo 1.1.0.
            // Le score aliments part de 100 et ne fait que descendre (malus seuls).
            // « Idéal » (>= 97) est donc réservé aux produits sans aucun défaut détecté.
            // Avant, le seuil était à 85 et les bonus compensaient les malus :
            // 100 % des produits scannés ressortaient « Idéal », l'échelle ne servait à rien.
            ScoringAlgorithm::Food => match (true) {
                $score >= 97 => self::Ideal,
                $score >= 85 => self::Good,
                $score >= 65 => self::Occasional,
                $score >= 40 => self::Limit,
                default => self::Discouraged,
            },
            // Laits infantiles : base conforme, jamais "déconseillé"
            ScoringAlgorithm::InfantFormula => match (true) {
                $score >= 95 => self::Ideal,
                $score >= 85 => self::Good,
                $score >= 70 => self::Occasional,
                default => self::Limit,
            },
        };
    }

    /** Libellé affiché, selon l'algorithme. */
    public function label(ScoringAlgorithm $algorithm): string
    {
        return match ($algorithm) {
            ScoringAlgorithm::Food => match ($this) {
                self::Ideal => 'Idéal pour bébé',
                self::Good => 'Bon choix',
                self::Occasional => 'Occasionnel',
                self::Limit => 'À limiter',
                self::Discouraged => 'À éviter',
            },
            ScoringAlgorithm::InfantFormula => match ($this) {
                self::Ideal => 'Excellent pour bébé',
                self::Good => 'Bon choix',
                default => 'Conforme',
            },
        };
    }

    /** Bornes de l'échelle aliments (affichage page Infos). */
    public function min(): int
    {
        return match ($this) {
            self::Ideal => 97,
            self::Good => 85,
            self::Occasional => 65,
            self::Limit => 40,
            self::Discouraged => 0,
        };
    }

    public function max(): int
    {
        return match ($this) {
            self::Ideal => 100,
            self::Good => 96,
            self::Occasional => 84,
            self::Limit => 64,
            self::Discouraged => 39,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ideal => 'Composition optimale, recommandé',
            self::Good => 'Adapté à votre enfant',
            self::Occasional => 'Acceptable de temps en temps',
            self::Limit => 'À consommer rarement',
            self::Discouraged => 'Plusieurs critères nutritionnels dépassés',
        };
    }
}
