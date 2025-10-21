<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251010120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for Ranch Booking API';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ranch_booking (id UUID NOT NULL, resource VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(64) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, horse_name VARCHAR(255) DEFAULT NULL, slot_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, slot_end TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, price NUMERIC(8, 2) NOT NULL, status VARCHAR(32) NOT NULL, source VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, payment_ref VARCHAR(255) DEFAULT NULL, synced_from VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_ranch_booking_uuid ON ranch_booking (id)');
        $this->addSql('CREATE INDEX idx_ranch_booking_resource_slot ON ranch_booking (resource, slot_start, slot_end)');
        $this->addSql("COMMENT ON COLUMN ranch_booking.slot_start IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ranch_booking.slot_end IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ranch_booking.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ranch_booking.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE ranch_booking_history (id SERIAL NOT NULL, booking_id UUID NOT NULL, booking_uuid UUID NOT NULL, changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, old_status VARCHAR(32) DEFAULT NULL, new_status VARCHAR(32) NOT NULL, changed_by VARCHAR(191) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql("COMMENT ON COLUMN ranch_booking_history.changed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX IDX_55ED886F3301C60 ON ranch_booking_history (booking_id)');
        $this->addSql('ALTER TABLE ranch_booking_history ADD CONSTRAINT FK_55ED886F3301C60 FOREIGN KEY (booking_id) REFERENCES ranch_booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ranch_booking_history DROP CONSTRAINT FK_55ED886F3301C60');
        $this->addSql('DROP TABLE ranch_booking_history');
        $this->addSql('DROP TABLE ranch_booking');
    }
}
