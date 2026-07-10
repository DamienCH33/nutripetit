<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime scan_sessions.baby_age_months : colonne morte, jamais alimentée
 * (le setter n'a jamais été appelé — l'âge n'est snapshoté que dans
 * score_results.baby_age_months, qui est conservé).
 */
final class Version20260709100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop de la colonne morte scan_sessions.baby_age_months';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scan_sessions DROP baby_age_months');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scan_sessions ADD baby_age_months INT DEFAULT NULL');
    }
}
