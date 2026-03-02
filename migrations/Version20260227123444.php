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
        // 1) Ajout colonne avec DEFAULT (important)
        $this->addSql("ALTER TABLE partner ADD type VARCHAR(20) DEFAULT 'partner'");
    
        // 2) Backfill lignes existantes (sécurité)
        $this->addSql("UPDATE partner SET type = 'partner' WHERE type IS NULL");
    
        // 3) Met NOT NULL + garde le default (au choix)
        $this->addSql("ALTER TABLE partner ALTER COLUMN type SET NOT NULL");
        // optionnel: garder default (pratique)
        $this->addSql("ALTER TABLE partner ALTER COLUMN type SET DEFAULT 'partner'");
    
        // Tes autres colonnes images (nullable OK)
        $this->addSql("ALTER TABLE partner ADD hero_image_file_name VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE partner ADD image2_file_name VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE partner ADD image3_file_name VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
{
    $this->addSql("ALTER TABLE partner DROP COLUMN type");
    $this->addSql("ALTER TABLE partner DROP COLUMN hero_image_file_name");
    $this->addSql("ALTER TABLE partner DROP COLUMN image2_file_name");
    $this->addSql("ALTER TABLE partner DROP COLUMN image3_file_name");
}
}
