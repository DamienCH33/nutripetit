<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610133207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE score_results ADD scan_count INT NOT NULL');
        $this->addSql('ALTER TABLE score_results ADD first_scanned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE score_results ADD last_scanned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_session_product ON score_results (scan_session_id, product_ean)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_session_product');
        $this->addSql('ALTER TABLE score_results DROP scan_count');
        $this->addSql('ALTER TABLE score_results DROP first_scanned_at');
        $this->addSql('ALTER TABLE score_results DROP last_scanned_at');
    }
}
