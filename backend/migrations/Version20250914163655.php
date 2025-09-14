<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250914163655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize horse fields and introduce stall_unit_id';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->addSql('CREATE TEMPORARY TABLE __temp_horse AS SELECT id, name, notes, owner_id, medical_history, medication, gender, date_of_birth FROM horse');
            $this->addSql('DROP TABLE horse');
            $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, age INTEGER NOT NULL, date_of_birth DATETIME DEFAULT NULL, breed VARCHAR(255) NOT NULL, gender VARCHAR(255) DEFAULT NULL, special_notes CLOB DEFAULT NULL, medical_history CLOB DEFAULT NULL, medication CLOB DEFAULT NULL, owner_id INTEGER NOT NULL, stall_unit_id INTEGER DEFAULT NULL, CONSTRAINT FK_HORSE_OWNER FOREIGN KEY (owner_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_HORSE_STALL_UNIT FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_HORSE_OWNER ON horse (owner_id)');
            $this->addSql('CREATE INDEX IDX_HORSE_STALL_UNIT ON horse (stall_unit_id)');
            $this->addSql('INSERT INTO horse (id, name, age, date_of_birth, breed, gender, special_notes, medical_history, medication, owner_id, stall_unit_id) SELECT id, name, 0, date_of_birth, "", gender, notes, medical_history, medication, owner_id, NULL FROM __temp_horse');
            $this->addSql('DROP TABLE __temp_horse');
        } else {
            $this->addSql('ALTER TABLE horse ADD age INT NOT NULL DEFAULT 0');
            $this->addSql('ALTER TABLE horse ADD breed VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE horse CHANGE notes special_notes LONGTEXT DEFAULT NULL');
            $this->addSql('ALTER TABLE horse ADD stall_unit_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_HORSE_STALL_UNIT ON horse (stall_unit_id)');
            $this->addSql('ALTER TABLE horse ADD CONSTRAINT FK_HORSE_STALL_UNIT FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->addSql('CREATE TEMPORARY TABLE __temp_horse AS SELECT id, name, age, date_of_birth, breed, gender, special_notes, medical_history, medication, owner_id, stall_unit_id FROM horse');
            $this->addSql('DROP TABLE horse');
            $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, notes CLOB DEFAULT NULL, owner_id INTEGER NOT NULL, medical_history CLOB DEFAULT NULL, medication CLOB DEFAULT NULL, gender VARCHAR(255) DEFAULT NULL, date_of_birth DATETIME DEFAULT NULL, CONSTRAINT FK_HORSE_OWNER FOREIGN KEY (owner_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_HORSE_OWNER ON horse (owner_id)');
            $this->addSql('INSERT INTO horse (id, name, notes, owner_id, medical_history, medication, gender, date_of_birth) SELECT id, name, special_notes, owner_id, medical_history, medication, gender, date_of_birth FROM __temp_horse');
            $this->addSql('DROP TABLE __temp_horse');
        } else {
            $this->addSql('ALTER TABLE horse DROP FOREIGN KEY FK_HORSE_STALL_UNIT');
            $this->addSql('DROP INDEX IDX_HORSE_STALL_UNIT ON horse');
            $this->addSql('ALTER TABLE horse DROP age');
            $this->addSql('ALTER TABLE horse DROP breed');
            $this->addSql('ALTER TABLE horse CHANGE special_notes notes LONGTEXT DEFAULT NULL');
            $this->addSql('ALTER TABLE horse DROP stall_unit_id');
        }
    }
}
