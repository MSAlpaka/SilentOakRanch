<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250912100150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update documentation entity with new fields and remove legacy ones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE documentation ADD COLUMN type VARCHAR(255) NOT NULL");
        $this->addSql("ALTER TABLE documentation ADD COLUMN created_at DATETIME NOT NULL");
        $this->addSql("ALTER TABLE documentation ADD COLUMN updated_at DATETIME NOT NULL");
        $this->addSql("ALTER TABLE documentation ADD COLUMN photos CLOB DEFAULT NULL");
        $this->addSql("ALTER TABLE documentation ADD COLUMN videos CLOB DEFAULT NULL");
        $this->addSql("ALTER TABLE documentation ADD COLUMN metrics CLOB DEFAULT NULL");
        $this->addSql("ALTER TABLE documentation DROP COLUMN date");
        $this->addSql("ALTER TABLE documentation DROP COLUMN images");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE documentation ADD COLUMN date DATETIME NOT NULL");
        $this->addSql("ALTER TABLE documentation ADD COLUMN images CLOB DEFAULT NULL");
        $this->addSql("ALTER TABLE documentation DROP COLUMN type");
        $this->addSql("ALTER TABLE documentation DROP COLUMN created_at");
        $this->addSql("ALTER TABLE documentation DROP COLUMN updated_at");
        $this->addSql("ALTER TABLE documentation DROP COLUMN photos");
        $this->addSql("ALTER TABLE documentation DROP COLUMN videos");
        $this->addSql("ALTER TABLE documentation DROP COLUMN metrics");
    }
}
