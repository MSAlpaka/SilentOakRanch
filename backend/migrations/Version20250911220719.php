<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250911220719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update invoice table to new structure';
    }

    public function up(Schema $schema): void
    {
        // remove legacy columns
        $this->addSql('DROP INDEX IF EXISTS UNIQ_9065174496901F54');
        $this->addSql('ALTER TABLE invoice DROP COLUMN user');
        $this->addSql('ALTER TABLE invoice DROP COLUMN number');
        $this->addSql('ALTER TABLE invoice DROP COLUMN period');
        $this->addSql('ALTER TABLE invoice DROP COLUMN total');
        $this->addSql('ALTER TABLE invoice DROP COLUMN stripe_invoice_id');

        // add new columns
        $this->addSql('ALTER TABLE invoice ADD COLUMN user_id INTEGER NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN booking_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN scale_booking_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN stripe_payment_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN currency VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN pdf_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN updated_at DATETIME NOT NULL');

        // add foreign keys and indexes
        $this->addSql('CREATE INDEX IDX_90651744A76ED395 ON invoice (user_id)');
        $this->addSql('CREATE INDEX IDX_906517443301C60 ON invoice (booking_id)');
        $this->addSql('CREATE INDEX IDX_9065174477C02832 ON invoice (scale_booking_id)');
        // SQLite does not support adding foreign key constraints via ALTER TABLE
    }

    public function down(Schema $schema): void
    {
        // drop foreign keys and indexes
        // foreign key constraints were not added in up() due to SQLite limitations
        $this->addSql('DROP INDEX IDX_90651744A76ED395');
        $this->addSql('DROP INDEX IDX_906517443301C60');
        $this->addSql('DROP INDEX IDX_9065174477C02832');

        // remove new columns
        $this->addSql('ALTER TABLE invoice DROP COLUMN user_id');
        $this->addSql('ALTER TABLE invoice DROP COLUMN booking_id');
        $this->addSql('ALTER TABLE invoice DROP COLUMN scale_booking_id');
        $this->addSql('ALTER TABLE invoice DROP COLUMN stripe_payment_id');
        $this->addSql('ALTER TABLE invoice DROP COLUMN amount');
        $this->addSql('ALTER TABLE invoice DROP COLUMN currency');
        $this->addSql('ALTER TABLE invoice DROP COLUMN pdf_path');
        $this->addSql('ALTER TABLE invoice DROP COLUMN updated_at');

        // restore old columns
        $this->addSql('ALTER TABLE invoice ADD COLUMN user VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN number VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN period VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN total NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE invoice ADD COLUMN stripe_invoice_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9065174496901F54 ON invoice (number)');
    }
}
