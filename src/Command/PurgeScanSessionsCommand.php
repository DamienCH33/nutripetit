<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RGPD (limitation de conservation) : purge les sessions de scan inactives
 * depuis plus de 13 mois, et leurs ScoreResult associés.
 * À planifier en cron (Railway scheduled job ou crontab) : 1x/jour.
 */
#[AsCommand(
    name: 'app:purge-scan-sessions',
    description: 'Supprime les sessions de scan inactives depuis plus de 13 mois (RGPD)',
)]
final class PurgeScanSessionsCommand extends Command
{
    private const RETENTION_MONTHS = 13;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cutoff = new \DateTimeImmutable(\sprintf('-%d months', self::RETENTION_MONTHS));

        // Les ScoreResult liés d'abord (FK), puis les sessions.
        $deletedResults = $this->em->createQuery(
            'DELETE FROM App\Entity\ScoreResult r WHERE r.scanSession IN (
                SELECT s FROM App\Entity\ScanSession s WHERE s.lastActiveAt < :cutoff
            )',
        )->setParameter('cutoff', $cutoff)->execute();

        $deletedSessions = $this->em->createQuery(
            'DELETE FROM App\Entity\ScanSession s WHERE s.lastActiveAt < :cutoff',
        )->setParameter('cutoff', $cutoff)->execute();

        $io->success(\sprintf(
            '%d session(s) et %d résultat(s) de scan purgés (inactifs depuis le %s).',
            $deletedSessions,
            $deletedResults,
            $cutoff->format('d/m/Y'),
        ));

        return Command::SUCCESS;
    }
}
