<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Valide qu'un code-barres respecte le format EAN-13.
 *
 * EAN-13 = 13 chiffres dont le dernier est une clé de contrôle (checksum).
 */
final class Ean13Validator
{
    public function isValid(string $ean13): bool
    {
        // Vérifie que la chaîne contient exactement 13 chiffres
        if (!preg_match('/^\d{13}$/', $ean13)) {
            return false;
        }

        $digits = str_split($ean13);
        $checksum = (int) array_pop($digits);

        // Calcul du checksum selon la formule EAN-13
        $sum = 0;
        foreach ($digits as $index => $digit) {
            $sum += (int) $digit * (0 === $index % 2 ? 1 : 3);
        }

        $calculatedChecksum = (10 - ($sum % 10)) % 10;

        return $checksum === $calculatedChecksum;
    }
}
