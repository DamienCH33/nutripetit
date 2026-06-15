<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'une règle évaluée pour un produit.
 */
enum RuleStatus: string
{
    /** La règle s'est déclenchée et impacte le score (malus ou bonus). */
    case Triggered = 'triggered';

    /** Le contrôle a été vérifié positivement (ex. "Sans sucre ✓"), impact nul. */
    case Satisfied = 'satisfied';
}
