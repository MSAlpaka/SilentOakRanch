<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250713171922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, stall_unit_id INTEGER NOT NULL, horse_id INTEGER DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, user VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, date_from DATETIME NOT NULL, date_to DATETIME DEFAULT NULL, is_confirmed BOOLEAN NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, CONSTRAINT FK_E00CEDDE26077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id), CONSTRAINT FK_E00CEDDE76B275AD FOREIGN KEY (horse_id) REFERENCES horse (id))');
        $this->addSql('CREATE INDEX IDX_E00CEDDE26077DEC ON booking (stall_unit_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE76B275AD ON booking (horse_id)');
        $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, owner_id INTEGER NOT NULL, current_location_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, gender VARCHAR(255) NOT NULL, date_of_birth DATE NOT NULL, notes CLOB DEFAULT NULL, CONSTRAINT FK_629A2F187E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id), CONSTRAINT FK_629A2F18B8998A57 FOREIGN KEY (current_location_id) REFERENCES stall_unit (id))');
        $this->addSql('CREATE INDEX IDX_629A2F187E3C61F9 ON horse (owner_id)');
        $this->addSql('CREATE INDEX IDX_629A2F18B8998A57 ON horse (current_location_id)');
        $this->addSql('CREATE TABLE invoice (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user VARCHAR(255) NOT NULL, number VARCHAR(255) NOT NULL, period VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(255) NOT NULL, total NUMERIC(10, 2) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9065174496901F54 ON invoice (number)');
        $this->addSql('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, invoice_id INTEGER NOT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, booking_type VARCHAR(255) DEFAULT NULL, booking_id CHAR(36) DEFAULT NULL --(DC2Type:guid)
        , CONSTRAINT FK_1DDE477B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id))');
        $this->addSql('CREATE INDEX IDX_1DDE477B2989F1FD ON invoice_item (invoice_id)');
        $this->addSql('CREATE TABLE pricing_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(255) NOT NULL, target VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, unit VARCHAR(255) NOT NULL, active_from DATE DEFAULT NULL, active_to DATE DEFAULT NULL, notes CLOB DEFAULT NULL, requires_subscription BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE stall_unit (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, area VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, horse_id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, starts_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , next_due DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , interval VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, auto_renew BOOLEAN NOT NULL, CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id), CONSTRAINT FK_A3C664D376B275AD FOREIGN KEY (horse_id) REFERENCES horse (id))');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D376B275AD ON subscription (horse_id)');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, stall_unit_id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, due_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, origin VARCHAR(255) NOT NULL, related_id CHAR(36) DEFAULT NULL --(DC2Type:guid)
        , CONSTRAINT FK_527EDB2526077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id))');
        $this->addSql('CREATE INDEX IDX_527EDB2526077DEC ON task (stall_unit_id)');
        $this->addSql('CREATE TABLE task_assignment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, task_id INTEGER NOT NULL, user VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id))');
        $this->addSql('CREATE INDEX IDX_2CD60F158DB60186 ON task_assignment (task_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , role VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE horse');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_item');
        $this->addSql('DROP TABLE pricing_rule');
        $this->addSql('DROP TABLE stall_unit');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_assignment');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
