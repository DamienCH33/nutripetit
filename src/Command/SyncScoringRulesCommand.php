<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\ScoringRuleFixtures;
use App\Entity\ScoringRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise les règles de scoring en base (seed/upsert).
 */
#[AsCommand(
    name: 'app:sync-scoring-rules',
    description: 'Synchronise les règles de scoring en base (à lancer au déploiement)',
)]
final class SyncScoringRulesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $version = ScoringRuleFixtures::ALGO_VERSION;

        $deleted = $this->em->createQuery(
            'DELETE FROM App\Entity\ScoringRule r WHERE r.algoVersion = :v',
        )->setParameter('v', $version)->execute();

        $written = 0;
        foreach (ScoringRuleFixtures::getRules() as $data) {
            $rule = new ScoringRule(
                code: $data['code'],
                label: $data['label'],
                description: $data['description'],
                algoVersion: $version,
                pointsImpact: $data['pointsImpact'],
                sourceName: $data['sourceName'],
                sourceUrl: $data['sourceUrl'],
            );

            if (isset($data['thresholdValue'])) {
                $rule->setThresholdValue($data['thresholdValue']);
            }
            if (isset($data['thresholdUnit'])) {
                $rule->setThresholdUnit($data['thresholdUnit']);
            }
            if (isset($data['ageMinMonths'])) {
                $rule->setAgeMinMonths($data['ageMinMonths']);
            }
            if (isset($data['ageMaxMonths'])) {
                $rule->setAgeMaxMonths($data['ageMaxMonths']);
            }

            $this->em->persist($rule);
            ++$written;
        }

        $this->em->flush();

        $io->success(\sprintf('%d règle(s) supprimée(s), %d écrite(s) (version %s).', $deleted, $written, $version));

        return Command::SUCCESS;
    }
}
