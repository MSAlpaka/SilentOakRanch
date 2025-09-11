<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240713065000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add owner relation to horse table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horse ADD COLUMN owner_id INTEGER NOT NULL');
        $this->addSql('CREATE INDEX IDX_HORSE_OWNER ON horse (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_HORSE_OWNER');
        $this->addSql('ALTER TABLE horse DROP COLUMN owner_id');
    }
}
