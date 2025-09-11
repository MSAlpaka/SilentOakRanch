<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250911074004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add date_of_birth column to horse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horse ADD COLUMN date_of_birth DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horse DROP COLUMN date_of_birth');
    }
}
