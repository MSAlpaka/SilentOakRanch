<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250911062130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gender field to horse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horse ADD COLUMN gender VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horse DROP COLUMN gender');
    }
}
