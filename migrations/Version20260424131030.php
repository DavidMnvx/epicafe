<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la colonne highlights (points mis en avant) à shop_category.
 */
final class Version20260424131030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add highlights column to shop_category.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_category ADD highlights TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_category DROP highlights');
    }
}
