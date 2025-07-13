<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250713160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add price column to booking and is_default column to pricing_rule';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking ADD price NUMERIC(10, 2) DEFAULT NULL");
        $this->addSql("ALTER TABLE pricing_rule ADD is_default BOOLEAN NOT NULL DEFAULT FALSE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking DROP price");
        $this->addSql("ALTER TABLE pricing_rule DROP is_default");
    }
}
