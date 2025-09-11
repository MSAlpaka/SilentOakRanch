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
        $this->addSql("ALTER TABLE subscription ADD COLUMN stall_unit_id INTEGER DEFAULT NULL");
        $this->addSql("ALTER TABLE subscription ADD COLUMN subscription_type VARCHAR(255) NOT NULL DEFAULT 'user'");
        $this->addSql('CREATE INDEX IDX_A3C664D326077DEC ON subscription (stall_unit_id)');
        $this->addSql("ALTER TABLE stall_unit ADD monthly_rent NUMERIC(10, 2) NOT NULL DEFAULT '0.00'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_A3C664D326077DEC');
        $this->addSql('ALTER TABLE subscription DROP COLUMN stall_unit_id');
        $this->addSql('ALTER TABLE subscription DROP COLUMN subscription_type');
        $this->addSql('ALTER TABLE stall_unit DROP COLUMN monthly_rent');
    }
}
