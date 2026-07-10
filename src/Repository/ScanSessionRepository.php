<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScanSession;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ScanSession>
 */
class ScanSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScanSession::class);
    }

    public function findById(Uuid $id): ?ScanSession
    {
        return $this->find($id);
    }

    public function findByCookieToken(string $cookieToken): ?ScanSession
    {
        return $this->findOneBy(['cookieToken' => $cookieToken]);
    }

    /** @return list<ScanSession> */
    public function findInactiveSince(int $days): array
    {
        $threshold = new DateTimeImmutable("-{$days} days");

        /** @var list<ScanSession> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.lastActiveAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countTotalSessions(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nouvelles sessions par jour sur les N derniers jours.
     * Agrégation en PHP (portable, pas de fonction SQL date).
     *
     * @return list<array{day: string, sessions: int}>
     */
    public function countNewSessionsPerDay(int $days = 30): array
    {
        $since = new DateTimeImmutable(\sprintf('-%d days', $days));

        /** @var list<array{createdAt: DateTimeInterface}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.createdAt AS createdAt')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($rows as $r) {
            $day = $r['createdAt']->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        }
        ksort($byDay);

        $out = [];
        foreach ($byDay as $day => $sessions) {
            $out[] = ['day' => $day, 'sessions' => $sessions];
        }

        return $out;
    }

    /**
     * Répartition iOS / Android / Autre à partir du userAgent.
     * Heuristique simple, suffisante pour orienter la stratégie (PWA vs Play Store).
     *
     * @return array{ios: int, android: int, other: int}
     */
    public function countByPlatform(): array
    {
        /** @var list<array{ua: string|null}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.userAgent AS ua')
            ->getQuery()
            ->getResult();

        $out = ['ios' => 0, 'android' => 0, 'other' => 0];
        foreach ($rows as $r) {
            $ua = $r['ua'] ?? '';
            if (preg_match('/iphone|ipad|ipod/i', $ua)) {
                ++$out['ios'];
            } elseif (preg_match('/android/i', $ua)) {
                ++$out['android'];
            } else {
                ++$out['other'];
            }
        }

        return $out;
    }
}
