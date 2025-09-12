<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250912125816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE appointment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, horse_id INTEGER NOT NULL, owner_id INTEGER NOT NULL, service_provider_id INTEGER DEFAULT NULL, service_type_id INTEGER NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME NOT NULL, status VARCHAR(255) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, notes CLOB DEFAULT NULL, CONSTRAINT FK_FE38F84476B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE38F8447E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE38F844C6C98E06 FOREIGN KEY (service_provider_id) REFERENCES service_provider (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE38F844AC8DE0F FOREIGN KEY (service_type_id) REFERENCES service_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FE38F84476B275AD ON appointment (horse_id)');
        $this->addSql('CREATE INDEX IDX_FE38F8447E3C61F9 ON appointment (owner_id)');
        $this->addSql('CREATE INDEX IDX_FE38F844C6C98E06 ON appointment (service_provider_id)');
        $this->addSql('CREATE INDEX IDX_FE38F844AC8DE0F ON appointment (service_type_id)');
        $this->addSql('CREATE TABLE service_provider (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, notes CLOB DEFAULT NULL, active BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE service_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, provider_type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, default_duration_minutes INTEGER NOT NULL, base_price NUMERIC(10, 2) NOT NULL, taxable BOOLEAN NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE appointment');
        $this->addSql('DROP TABLE service_provider');
        $this->addSql('DROP TABLE service_type');
    }
}
