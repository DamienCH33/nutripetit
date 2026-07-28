<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\BabyProfile\BabyProfileProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BabyProfileController extends AbstractController
{
    #[Route('/api/baby-profile', name: 'api_baby_profile', methods: ['GET'])]
    public function index(BabyProfileProvider $provider): JsonResponse
    {
        $data = $provider->getBabyProfileData();

        return $this->json($data);
    }
}
