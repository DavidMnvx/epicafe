<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les colonnes pull_quote et pull_quote_author à shop_category.
 */
final class Version20260425074808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add editable pull quote (text + author) to shop_category.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_category ADD pull_quote TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE shop_category ADD pull_quote_author VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_category DROP pull_quote');
        $this->addSql('ALTER TABLE shop_category DROP pull_quote_author');
    }
}
