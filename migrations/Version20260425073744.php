<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la colonne image_url (URL externe / fallback) à shop_category.
 */
final class Version20260425073744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_url column to shop_category (external URL fallback).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_category ADD image_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_category DROP image_url');
    }
}
