<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250715000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add documentation table and optional horse medical fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE documentation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, booking_id INTEGER NOT NULL, date DATETIME NOT NULL, notes CLOB DEFAULT NULL, images CLOB DEFAULT NULL, CONSTRAINT FK_DOCUMENTATION_BOOKING FOREIGN KEY (booking_id) REFERENCES booking (id))');
        $this->addSql('CREATE INDEX IDX_DOCUMENTATION_BOOKING ON documentation (booking_id)');
        $this->addSql('ALTER TABLE horse ADD COLUMN medical_history CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE horse ADD COLUMN medication CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE documentation');
        $this->addSql('ALTER TABLE horse DROP COLUMN medical_history');
        $this->addSql('ALTER TABLE horse DROP COLUMN medication');
    }
}

