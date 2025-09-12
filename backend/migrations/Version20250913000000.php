<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250913000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create agreement table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agreement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, type VARCHAR(255) NOT NULL, version VARCHAR(255) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, consent_given BOOLEAN NOT NULL, consent_at DATETIME NOT NULL, signed_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, CONSTRAINT FK_AGREEMENT_USER FOREIGN KEY (user_id) REFERENCES user (id))');
        $this->addSql('CREATE INDEX IDX_AGREEMENT_USER_ID ON agreement (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE agreement');
    }
}
