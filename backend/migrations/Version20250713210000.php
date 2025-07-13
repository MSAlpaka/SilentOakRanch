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
        $this->addSql("ALTER TABLE subscription ADD end_date DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE subscription DROP end_date");
    }
}
