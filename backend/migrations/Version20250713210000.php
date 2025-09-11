<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250713210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add end_date column to subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD COLUMN end_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP COLUMN end_date');
    }
}
