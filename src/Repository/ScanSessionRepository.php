<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScanSession;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<ScanSession>
 */
class ScanSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScanSession::class);
    }

    public function findById(Ulid $id): ?ScanSession
    {
        return $this->find($id);
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
