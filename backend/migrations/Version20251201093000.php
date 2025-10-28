<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251201093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create contract table and add source UUID to booking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking ADD source_uuid UUID DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E00CEDDE89D40298 ON booking (source_uuid)');
        if ($this->connection->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->addSql("COMMENT ON COLUMN booking.source_uuid IS '(DC2Type:uuid)'");
        }

        $this->addSql('CREATE TABLE contract (id UUID NOT NULL, booking_id INT NOT NULL, path VARCHAR(255) NOT NULL, hash VARCHAR(128) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, signed_path VARCHAR(255) DEFAULT NULL, signed_hash VARCHAR(128) DEFAULT NULL, signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, audit_trail JSON NOT NULL, PRIMARY KEY(id))');
        if ($this->connection->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->addSql("COMMENT ON COLUMN contract.created_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN contract.updated_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN contract.signed_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN contract.audit_trail IS '(DC2Type:json)'");
        }
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DF7DFD0C3301C60 ON contract (booking_id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_DF7DFD0C3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_DF7DFD0C3301C60');
        $this->addSql('DROP TABLE contract');

        $this->addSql('DROP INDEX UNIQ_E00CEDDE89D40298');
        $this->addSql('ALTER TABLE booking DROP source_uuid');
    }
}
