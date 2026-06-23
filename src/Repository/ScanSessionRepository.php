<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScanSession;
use DateTimeImmutable;
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
}
