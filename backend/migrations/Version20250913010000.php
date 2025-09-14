<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250913010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add current_location_id to horse table with ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->addSql('CREATE TEMPORARY TABLE __temp_horse AS SELECT id, name, notes, owner_id, medical_history, medication, gender, date_of_birth FROM horse');
            $this->addSql('DROP TABLE horse');
            $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, notes CLOB DEFAULT NULL, owner_id INTEGER NOT NULL, medical_history CLOB DEFAULT NULL, medication CLOB DEFAULT NULL, gender VARCHAR(255) DEFAULT NULL, date_of_birth DATETIME DEFAULT NULL, current_location_id INTEGER DEFAULT NULL, CONSTRAINT FK_HORSE_CURRENT_LOCATION FOREIGN KEY (current_location_id) REFERENCES stall_unit (id) ON DELETE SET NULL)');
            $this->addSql('CREATE INDEX IDX_HORSE_OWNER ON horse (owner_id)');
            $this->addSql('CREATE INDEX IDX_HORSE_CURRENT_LOCATION ON horse (current_location_id)');
            $this->addSql('INSERT INTO horse (id, name, notes, owner_id, medical_history, medication, gender, date_of_birth) SELECT id, name, notes, owner_id, medical_history, medication, gender, date_of_birth FROM __temp_horse');
            $this->addSql('DROP TABLE __temp_horse');
        } else {
            $this->addSql('ALTER TABLE horse ADD current_location_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_HORSE_CURRENT_LOCATION ON horse (current_location_id)');
            $this->addSql('ALTER TABLE horse ADD CONSTRAINT FK_HORSE_CURRENT_LOCATION FOREIGN KEY (current_location_id) REFERENCES stall_unit (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->addSql('CREATE TEMPORARY TABLE __temp_horse AS SELECT id, name, notes, owner_id, medical_history, medication, gender, date_of_birth FROM horse');
            $this->addSql('DROP TABLE horse');
            $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, notes CLOB DEFAULT NULL, owner_id INTEGER NOT NULL, medical_history CLOB DEFAULT NULL, medication CLOB DEFAULT NULL, gender VARCHAR(255) DEFAULT NULL, date_of_birth DATETIME DEFAULT NULL)');
            $this->addSql('CREATE INDEX IDX_HORSE_OWNER ON horse (owner_id)');
            $this->addSql('INSERT INTO horse (id, name, notes, owner_id, medical_history, medication, gender, date_of_birth) SELECT id, name, notes, owner_id, medical_history, medication, gender, date_of_birth FROM __temp_horse');
            $this->addSql('DROP TABLE __temp_horse');
        } else {
            $this->addSql('ALTER TABLE horse DROP FOREIGN KEY FK_HORSE_CURRENT_LOCATION');
            $this->addSql('DROP INDEX IDX_HORSE_CURRENT_LOCATION ON horse');
            $this->addSql('ALTER TABLE horse DROP current_location_id');
        }
    }
}
