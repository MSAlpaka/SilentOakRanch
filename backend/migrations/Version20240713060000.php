<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240713060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create booking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, stall_unit_id INTEGER NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, horse_id INTEGER DEFAULT NULL, user VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, CONSTRAINT FK_BOOKING_STALL_UNIT FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id), CONSTRAINT FK_BOOKING_HORSE FOREIGN KEY (horse_id) REFERENCES horse (id))');
        $this->addSql('CREATE INDEX IDX_BOOKING_STALL_UNIT ON booking (stall_unit_id)');
        $this->addSql('CREATE INDEX IDX_BOOKING_HORSE ON booking (horse_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE booking');
    }
}
