<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240713070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add additional fields to booking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking ADD type VARCHAR(255) NOT NULL");
        $this->addSql("ALTER TABLE booking ADD label VARCHAR(255) NOT NULL");
        $this->addSql("ALTER TABLE booking ADD date_from DATETIME NOT NULL");
        $this->addSql("ALTER TABLE booking ADD date_to DATETIME DEFAULT NULL");
        $this->addSql("ALTER TABLE booking ADD is_confirmed BOOLEAN NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking DROP type");
        $this->addSql("ALTER TABLE booking DROP label");
        $this->addSql("ALTER TABLE booking DROP date_from");
        $this->addSql("ALTER TABLE booking DROP date_to");
        $this->addSql("ALTER TABLE booking DROP is_confirmed");
    }
}
