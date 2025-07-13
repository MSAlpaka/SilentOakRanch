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
        $this->addSql("CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, stall_unit_id INT NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, horse_id INT DEFAULT NULL, user VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, INDEX IDX_BOOKING_STALL_UNIT (stall_unit_id), INDEX IDX_BOOKING_HORSE (horse_id), PRIMARY KEY(id))");
        $this->addSql("ALTER TABLE booking ADD CONSTRAINT FK_BOOKING_STALL_UNIT FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE booking ADD CONSTRAINT FK_BOOKING_HORSE FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE booking');
    }
}
