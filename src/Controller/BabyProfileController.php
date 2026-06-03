<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du profil bébé.
 *
 * En V1, le profil est stocké en localStorage côté client (pas d'authentification).
 * Le contrôleur fournit la liste des tranches d'âge supportées par le scoring.
 *
 * En V2, ces données seront persistées en DB via une entité BabyProfile
 * liée à un User authentifié, avec synchronisation cross-device.
 */
final class BabyProfileController extends AbstractController
{
    #[Route('/app/profil-bebe', name: 'app_pwa_baby_profile', methods: ['GET'])]
    public function index(): Response
    {
        // Tranches d'âge correspondant aux barèmes du moteur de scoring
        $ageRanges = [
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

        return $this->render('pages/app/baby_profile.html.twig', [
            'ageRanges' => $ageRanges,
        ]);
    }
}
