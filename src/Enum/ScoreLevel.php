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
            ScoringAlgorithm::Food => match (true) {
                $score >= 97 => self::Ideal,
                $score >= 85 => self::Good,
                $score >= 65 => self::Occasional,
                $score >= 40 => self::Limit,
                default => self::Discouraged,
            },
            // Laits infantiles (algo v3.0.0) : base 60, plancher 60, plage réelle 60-82.
            // Tout lait UE est conforme et sûr : on ne descend jamais à "déconseillé".
            // Seuils recalés sur la plage atteignable pour que l'échelle discrimine.
            ScoringAlgorithm::InfantFormula => match (true) {
                $score >= 78 => self::Ideal,        // tous/presque tous les atouts
                $score >= 70 => self::Good,         // plusieurs atouts
                $score >= 64 => self::Occasional,   // un ou deux atouts
                default => self::Limit,        // 60-63 : conforme, sans atout premium
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
            // Registre "qualité de formulation" : un lait est l'alimentation ESSENTIELLE
            // du bébé, jamais un aliment "à limiter" ou "occasionnel". Le score nuance
            // seulement la richesse des atouts optionnels (DHA obligatoire ne compte pas).
            // 5 cas explicites (plus de default => 'Conforme', qui écrasait limit/occasional).
            ScoringAlgorithm::InfantFormula => match ($this) {
                self::Ideal => 'Excellent lait pour bébé',
                self::Good => 'Très bon lait pour bébé',
                self::Occasional => 'Bon lait pour bébé',
                self::Limit => 'Lait conforme et sûr pour bébé',
                self::Discouraged => 'Lait conforme et sûr pour bébé',
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
