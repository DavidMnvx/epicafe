<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227123444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE partner ADD type VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE partner ADD hero_image_file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE partner ADD image2_file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE partner ADD image3_file_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE partner DROP type');
        $this->addSql('ALTER TABLE partner DROP hero_image_file_name');
        $this->addSql('ALTER TABLE partner DROP image2_file_name');
        $this->addSql('ALTER TABLE partner DROP image3_file_name');
    }
}
