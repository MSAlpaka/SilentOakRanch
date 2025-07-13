<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240713071000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add price column to booking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking ADD price NUMERIC(10, 2) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking DROP price");
    }
}
