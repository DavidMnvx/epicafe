<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219122708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event ADD menu_starter VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD menu_main VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD menu_dessert VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD menu_price NUMERIC(6, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD image_file_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP menu_starter');
        $this->addSql('ALTER TABLE event DROP menu_main');
        $this->addSql('ALTER TABLE event DROP menu_dessert');
        $this->addSql('ALTER TABLE event DROP menu_price');
        $this->addSql('ALTER TABLE event DROP image_file_name');
    }
}
