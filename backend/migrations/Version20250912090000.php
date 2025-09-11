<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250912090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate scale_booking table with relations and new fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS scale_booking');
        $this->addSql('CREATE TABLE scale_booking (id CHAR(36) NOT NULL, horse_id INTEGER NOT NULL, owner_id INTEGER NOT NULL, slot DATETIME NOT NULL, weight DOUBLE PRECISION DEFAULT NULL, booking_type VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, qr_token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_SCALE_BOOKING_HORSE FOREIGN KEY (horse_id) REFERENCES horse (id), CONSTRAINT FK_SCALE_BOOKING_OWNER FOREIGN KEY (owner_id) REFERENCES user (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SCALE_BOOKING_QRTOKEN ON scale_booking (qr_token)');
        $this->addSql('CREATE INDEX IDX_SCALE_BOOKING_HORSE_ID ON scale_booking (horse_id)');
        $this->addSql('CREATE INDEX IDX_SCALE_BOOKING_OWNER_ID ON scale_booking (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scale_booking');
    }
}
