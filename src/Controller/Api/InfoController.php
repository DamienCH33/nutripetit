<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Info\InfoDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    #[Route('/api/info', name: 'api_info', methods: ['GET'])]
    public function index(InfoDataProvider $provider): JsonResponse
    {
        $data = $provider->getInfoData();

        $data['rules'] = array_map(
            static fn($rule): array => [
                'code' => $rule->getCode(),
                'label' => $rule->getLabel(),
                'description' => $rule->getDescription(),
                'pointsImpact' => $rule->getPointsImpact(),
                'sourceName' => $rule->getSourceName(),
                'sourceUrl' => $rule->getSourceUrl(),
                'ageMinMonths' => $rule->getAgeMinMonths(),
                'ageMaxMonths' => $rule->getAgeMaxMonths(),
            ],
            $data['rules'],
        );

        return $this->json($data);
    }
}
