<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250713172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription type and stall relation; add monthly rent to stall unit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE subscription ADD stall_unit_id INTEGER DEFAULT NULL");
        $this->addSql("ALTER TABLE subscription ADD subscription_type VARCHAR(255) NOT NULL DEFAULT 'user'");
        $this->addSql('CREATE INDEX IDX_A3C664D326077DEC ON subscription (stall_unit_id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D326077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("ALTER TABLE stall_unit ADD monthly_rent NUMERIC(10, 2) NOT NULL DEFAULT '0.00'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D326077DEC');
        $this->addSql('DROP INDEX IDX_A3C664D326077DEC');
        $this->addSql('ALTER TABLE subscription DROP stall_unit_id');
        $this->addSql('ALTER TABLE subscription DROP subscription_type');
        $this->addSql('ALTER TABLE stall_unit DROP monthly_rent');
    }
}
