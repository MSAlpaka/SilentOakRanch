<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240713065000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add owner relation to horse table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE horse ADD owner_id INT NOT NULL");
        $this->addSql("ALTER TABLE horse ADD CONSTRAINT FK_HORSE_OWNER FOREIGN KEY (owner_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("CREATE INDEX IDX_HORSE_OWNER ON horse (owner_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE horse DROP FOREIGN KEY FK_HORSE_OWNER");
        $this->addSql("DROP INDEX IDX_HORSE_OWNER ON horse");
        $this->addSql("ALTER TABLE horse DROP owner_id");
    }
}
