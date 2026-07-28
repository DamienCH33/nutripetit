<?php

declare(strict_types=1);

namespace App\Service\BabyProfile;

final class BabyProfileProvider
{
    /**
     * @return list<array{code: string, label: string, minMonths: int, maxMonths: int, description: string}>
     */
    public function getBabyProfileData(): array
    {
        return [
            [
                'code' => 'newborn',
                'label' => 'Nouveau-né',
                'minMonths' => 0,
                'maxMonths' => 5,
                'description' => 'Alimentation lactée exclusive',
            ],
            [
                'code' => 'infant',
                'label' => 'Nourrisson',
                'minMonths' => 6,
                'maxMonths' => 11,
                'description' => 'Diversification alimentaire',
            ],
            [
                'code' => 'toddler',
                'label' => 'Jeune enfant',
                'minMonths' => 12,
                'maxMonths' => 36,
                'description' => 'Alimentation diversifiée',
            ],
        ];
    }
}
