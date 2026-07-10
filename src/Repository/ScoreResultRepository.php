<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ScanSession;
use App\Entity\ScoreResult;
use DateTimeImmutable;
use DateTimeInterface;
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
    public function findRecentBySession(ScanSession $session, int $limit = 10, int $offset = 0): array
    {
        /** @var list<ScoreResult> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.scanSession = :session')
            ->setParameter('session', $session)
            ->orderBy('s.calculatedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countBySession(ScanSession $session): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.scanSession = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->getSingleScalarResult();
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

    /**
     * Efface tous les résultats d'une session (droit à l'effacement RGPD).
     *
     * @return int Nombre de lignes supprimées
     */
    public function deleteAllForSession(ScanSession $session): int
    {
        return (int) $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.scanSession = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->execute();
    }

    public function findForSessionAndProduct(
        ScanSession $session,
        Product $product,
    ): ?ScoreResult {
        return $this->findOneBy([
            'scanSession' => $session,
            'product' => $product,
        ]);
    }

    /**
     * Nombre total de scans (somme des scanCount, car un ScoreResult
     * agrège les re-scans d'un même produit par une même session).
     */
    public function countTotalScans(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.scanCount), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top des produits les plus scannés.
     *
     * @return list<array{ean: string, name: string, scans: int}>
     */
    public function findMostScannedProducts(int $limit = 20): array
    {
        /** @var list<array{ean: string, name: string, scans: int|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('p.ean AS ean', 'p.name AS name', 'SUM(s.scanCount) AS scans')
            ->join('s.product', 'p')
            ->groupBy('p.ean', 'p.name')
            ->orderBy('scans', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $r): array => [
                'ean' => $r['ean'],
                'name' => $r['name'],
                'scans' => (int) $r['scans'],
            ],
            $rows,
        );
    }

    /**
     * Répartition des scans par niveau de score (ideal, good, ...).
     *
     * @return array<string, int> clé = level, valeur = nombre de scans
     */
    public function countScansByLevel(): array
    {
        /** @var list<array{level: string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.level AS level', 'SUM(s.scanCount) AS total')
            ->groupBy('s.level')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['level']] = (int) $r['total'];
        }

        return $out;
    }

    /**
     * Scans par jour sur les N derniers jours (pour une courbe).
     * Agrégation faite en PHP pour rester portable (pas de fonction SQL date).
     *
     * @return list<array{day: string, scans: int}>
     */
    public function countScansPerDay(int $days = 30): array
    {
        $since = new DateTimeImmutable(\sprintf('-%d days', $days));

        /** @var list<array{lastScannedAt: DateTimeInterface, scanCount: int}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.lastScannedAt AS lastScannedAt', 's.scanCount AS scanCount')
            ->andWhere('s.lastScannedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($rows as $r) {
            $day = $r['lastScannedAt']->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + (int) $r['scanCount'];
        }
        ksort($byDay);

        $out = [];
        foreach ($byDay as $day => $scans) {
            $out[] = ['day' => $day, 'scans' => $scans];
        }

        return $out;
    }

    /**
     * Répartition des tranches d'âge bébé renseignées lors des scans.
     *
     * @return array<int, int> clé = âge en mois, valeur = nombre de scans
     */
    public function countScansByBabyAge(): array
    {
        /** @var list<array{age: int, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.babyAgeMonths AS age', 'SUM(s.scanCount) AS total')
            ->andWhere('s.babyAgeMonths IS NOT NULL')
            ->groupBy('s.babyAgeMonths')
            ->orderBy('s.babyAgeMonths', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['age']] = (int) $r['total'];
        }

        return $out;
    }
}
