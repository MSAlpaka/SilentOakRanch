<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250911021123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe IDs to user and invoice tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD stripe_invoice_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP stripe_customer_id');
        $this->addSql('ALTER TABLE invoice DROP stripe_invoice_id');
    }
}
