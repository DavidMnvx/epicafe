<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire du formulaire d'événement les champs du « menu classique », qui
 * n'ont jamais servi : sur les 14 événements en base, menu_starter, menu_main,
 * menu_dessert, menu_dessert2 et menu sont vides partout. offer_type vaut
 * 'menu' pour tout le monde et ne discriminait donc rien.
 *
 * Les plats sont désormais saisis uniquement via product1/2/3.
 */
final class Version20260829180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused event menu columns (menu_starter, menu_main, menu_dessert, menu_dessert2, menu, offer_type).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE event
                DROP COLUMN IF EXISTS menu_starter,
                DROP COLUMN IF EXISTS menu_main,
                DROP COLUMN IF EXISTS menu_dessert,
                DROP COLUMN IF EXISTS menu_dessert2,
                DROP COLUMN IF EXISTS menu,
                DROP COLUMN IF EXISTS offer_type
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE event
                ADD COLUMN menu_starter VARCHAR(255) DEFAULT NULL,
                ADD COLUMN menu_main VARCHAR(255) DEFAULT NULL,
                ADD COLUMN menu_dessert VARCHAR(255) DEFAULT NULL,
                ADD COLUMN menu_dessert2 VARCHAR(255) DEFAULT NULL,
                ADD COLUMN menu TEXT DEFAULT NULL,
                ADD COLUMN offer_type VARCHAR(20) DEFAULT 'menu' NOT NULL
        SQL);
    }
}
