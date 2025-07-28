<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250713220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create scale_booking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE scale_booking (id CHAR(36) NOT NULL --(DC2Type:guid), booking_date_time DATETIME NOT NULL, customer_name VARCHAR(255) NOT NULL, customer_email VARCHAR(255) NOT NULL, customer_phone VARCHAR(255) DEFAULT NULL, horse_name VARCHAR(255) NOT NULL, estimated_weight NUMERIC(10, 2) DEFAULT NULL, actual_weight NUMERIC(10, 2) DEFAULT NULL, comment CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, qr_code CHAR(36) NOT NULL --(DC2Type:guid), redeemed_at DATETIME DEFAULT NULL, result_email_sent_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scale_booking');
    }
}
