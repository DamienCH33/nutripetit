<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Info\InfoDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    public function __construct(
        private readonly InfoDataProvider $infoDataProvider,
    ) {
    }

    //     #[Route('/app/infos', name: 'app_pwa_info', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/app/info.html.twig', $this->infoDataProvider->getInfoData());
    }
}
