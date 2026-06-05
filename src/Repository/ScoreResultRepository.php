<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ScanSession;
use App\Entity\ScoreResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScoreResult>
 */
class ScoreResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScoreResult::class);
    }

    /** @return list<ScoreResult> */
    public function findRecent(int $limit = 10): array
    {
        /** @var list<ScoreResult> $result */
        $result = $this->createQueryBuilder('s')
            ->orderBy('s.calculatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<ScoreResult> */
    public function findRecentBySession(ScanSession $session, int $limit = 10): array
    {
        /** @var list<ScoreResult> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.scanSession = :session')
            ->setParameter('session', $session)
            ->orderBy('s.calculatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findLatestForProduct(Product $product): ?ScoreResult
    {
        /** @var ScoreResult|null $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.product = :product')
            ->setParameter('product', $product)
            ->orderBy('s.calculatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }
}
