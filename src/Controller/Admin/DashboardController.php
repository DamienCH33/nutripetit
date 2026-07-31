<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ProductRepository;
use App\Repository\ScanSessionRepository;
use App\Repository\ScoreResultRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ScoreResultRepository $scoreResults,
        private readonly ScanSessionRepository $scanSessions,
        private readonly ProductRepository $products,
    ) {
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->displayUserName(true)
            ->displayUserAvatar(false)
            ->addMenuItems([
                MenuItem::linkToUrl('Retour à l\'app', 'fa fa-arrow-left', '/app'),
                MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out'),
            ]);
    }

    public function index(): Response
    {
        $scansByLevel = $this->scoreResults->countScansByLevel();
        $scansByAge = $this->scoreResults->countScansByBabyAge();

        // Regroupe les âges en tranches lisibles (0-6, 6-12, 12-18, 18-24, 24-36).
        $ageBuckets = ['0-6 mois' => 0, '6-12 mois' => 0, '12-18 mois' => 0, '18-24 mois' => 0, '24-36 mois' => 0];
        foreach ($scansByAge as $age => $count) {
            $bucket = match (true) {
                $age < 6 => '0-6 mois',
                $age < 12 => '6-12 mois',
                $age < 18 => '12-18 mois',
                $age < 24 => '18-24 mois',
                default => '24-36 mois',
            };
            $ageBuckets[$bucket] += $count;
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalScans' => $this->scoreResults->countTotalScans(),
            'totalSessions' => $this->scanSessions->countTotalSessions(),
            'totalProducts' => $this->products->count([]),
            'mostScanned' => $this->scoreResults->findMostScannedProducts(20),
            'scansPerDay' => $this->scoreResults->countScansPerDay(30),
            'sessionsPerDay' => $this->scanSessions->countNewSessionsPerDay(30),
            'scansByLevel' => $scansByLevel,
            'ageBuckets' => $ageBuckets,
            'platform' => $this->scanSessions->countByPlatform(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('NutriPetit — Administration');
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new LogicException('Interceptée par le firewall.');
    }
}
