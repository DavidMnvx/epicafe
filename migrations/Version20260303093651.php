<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303093651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_category ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_category ADD CONSTRAINT FK_2A1D5C57727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2A1D5C57727ACA70 ON menu_category (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_category DROP CONSTRAINT FK_2A1D5C57727ACA70');
        $this->addSql('DROP INDEX IDX_2A1D5C57727ACA70');
        $this->addSql('ALTER TABLE menu_category DROP parent_id');
    }
}
