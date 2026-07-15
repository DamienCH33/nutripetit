<?php

declare(strict_types=1);

namespace App\Command;

use App\Scoring\ScoringRulesProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Vérifie que chaque URL source des règles de scoring répond correctement.
 *
 * Un lien mort dans une source affaiblit la crédibilité (et la défendabilité)
 * du score affiché au parent. Cette commande permet de détecter les 404 avant
 * qu'un utilisateur ne tombe dessus — à lancer périodiquement ou en CI.
 */
#[AsCommand(
    name: 'app:check-source-links',
    description: 'Vérifie que les liens sources des règles de scoring répondent (pas de 404).',
)]
final class CheckSourceLinksCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // URLs uniques (plusieurs règles partagent parfois la même source).
        $urls = [];
        foreach (ScoringRulesProvider::getRules() as $rule) {
            $url = $rule['sourceUrl'] ?? null;
            if (\is_string($url) && '' !== $url) {
                $urls[$url] = true;
            }
        }
        $urls = array_keys($urls);

        $io->title(\sprintf('Vérification de %d lien(s) source', \count($urls)));

        $broken = [];
        foreach ($urls as $url) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 10,
                    'max_redirects' => 5,
                    'headers' => ['User-Agent' => 'NutriPetit-LinkChecker/1.0'],
                ]);
                $status = $response->getStatusCode();

                if ($status >= 400) {
                    $broken[] = [$url, (string) $status];
                    $io->writeln(\sprintf('  <error>✗ %d</error> %s', $status, $url));
                } else {
                    $io->writeln(\sprintf('  <info>✓ %d</info> %s', $status, $url));
                }
            } catch (Throwable $e) {
                $broken[] = [$url, $e->getMessage()];
                $io->writeln(\sprintf('  <error>✗ ERREUR</error> %s', $url));
            }
        }

        if ([] !== $broken) {
            $io->error(\sprintf('%d lien(s) cassé(s) :', \count($broken)));
            $io->table(['URL', 'Statut / Erreur'], $broken);

            return Command::FAILURE;
        }

        $io->success('Tous les liens sources répondent correctement.');

        return Command::SUCCESS;
    }
}
