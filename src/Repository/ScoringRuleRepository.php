<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScoringRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScoringRule>
 */
class ScoringRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScoringRule::class);
    }

    /** @return list<ScoringRule> */
    public function findActiveByVersion(string $algoVersion): array
    {
        /** @var list<ScoringRule> $result */
        $result = $this->createQueryBuilder('r')
            ->andWhere('r.algoVersion = :version')
            ->andWhere('r.isActive = true')
            ->setParameter('version', $algoVersion)
            ->orderBy('r.pointsImpact', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
