<?php

declare(strict_types=1);

namespace App\Service\Session;

use App\Entity\ScanSession;
use App\Repository\ScanSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class ScanSessionManager
{
    public const SESSION_COOKIE_NAME = 'np_session';
    public const SESSION_COOKIE_TTL_DAYS = 365;

    public function __construct(
        private readonly ScanSessionRepository $scanSessionRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function resolveScanSession(Request $request): ScanSession
    {
        $cookieValue = $request->cookies->get(self::SESSION_COOKIE_NAME);

        if (\is_string($cookieValue) && Uuid::isValid($cookieValue)) {
            $session = $this->scanSessionRepository->findById(Uuid::fromString($cookieValue));
            if (null !== $session) {
                return $session;
            }
        }

        $session = new ScanSession($request->headers->get('User-Agent', 'unknown'));
        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }
}
