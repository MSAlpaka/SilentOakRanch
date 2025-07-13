<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240713072000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_default column to pricing_rule table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE pricing_rule ADD is_default BOOLEAN NOT NULL DEFAULT FALSE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE pricing_rule DROP is_default");
    }
}
