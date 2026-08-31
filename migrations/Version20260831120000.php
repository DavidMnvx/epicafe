<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute un ordre explicite aux photos de la galerie.
 *
 * Jusqu'ici l'affichage suivait la date de création : impossible de choisir
 * quelle photo met en avant. Les photos existantes sont numérotées dans leur
 * ordre d'affichage actuel, pour que rien ne bouge à la mise à jour.
 */
final class Version20260831120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position column to gallery_photo for drag-and-drop ordering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gallery_photo ADD COLUMN "position" INT DEFAULT 0 NOT NULL');

        // Reprend l'ordre en vigueur avant la migration (date de prise de vue
        // décroissante, puis création) pour ne pas rebattre la galerie.
        $this->addSql(<<<'SQL'
            UPDATE gallery_photo AS g
            SET "position" = ranked.rang
            FROM (
                SELECT id, (ROW_NUMBER() OVER (ORDER BY taken_at DESC NULLS LAST, created_at DESC) - 1) AS rang
                FROM gallery_photo
            ) AS ranked
            WHERE g.id = ranked.id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gallery_photo DROP COLUMN "position"');
    }
}
