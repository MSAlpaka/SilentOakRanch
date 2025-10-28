<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251215091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit log table for WORM-compliant append-only tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, entity_type VARCHAR(191) NOT NULL, entity_id VARCHAR(191) NOT NULL, action VARCHAR(64) NOT NULL, hash VARCHAR(128) DEFAULT NULL, user_identifier VARCHAR(191) DEFAULT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, meta JSON NOT NULL, PRIMARY KEY(id))');
        if ($this->connection->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->addSql("COMMENT ON COLUMN audit_log.id IS '(DC2Type:uuid)'");
            $this->addSql("COMMENT ON COLUMN audit_log.timestamp IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN audit_log.meta IS '(DC2Type:json)'");
        }
        $this->addSql('CREATE INDEX idx_audit_timestamp_entity ON audit_log (timestamp, entity_type, entity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
