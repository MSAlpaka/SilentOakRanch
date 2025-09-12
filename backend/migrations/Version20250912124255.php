<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250912124255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE add_on (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL)');
        $this->addSql('CREATE TABLE agreement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, type VARCHAR(255) NOT NULL, version VARCHAR(255) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, consent_given BOOLEAN NOT NULL, consent_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , signed_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , status VARCHAR(255) NOT NULL, CONSTRAINT FK_2E655A24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2E655A24A76ED395 ON agreement (user_id)');
        $this->addSql('CREATE TABLE booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, stall_unit_id INTEGER NOT NULL, horse_id INTEGER DEFAULT NULL, package_id INTEGER DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, user VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, date_from DATETIME NOT NULL, date_to DATETIME DEFAULT NULL, is_confirmed BOOLEAN NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, CONSTRAINT FK_E00CEDDE26077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E00CEDDE76B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E00CEDDEF44CABFF FOREIGN KEY (package_id) REFERENCES package (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE26077DEC ON booking (stall_unit_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE76B275AD ON booking (horse_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEF44CABFF ON booking (package_id)');
        $this->addSql('CREATE TABLE booking_add_on (booking_id INTEGER NOT NULL, add_on_id INTEGER NOT NULL, PRIMARY KEY(booking_id, add_on_id), CONSTRAINT FK_3E89A5C13301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3E89A5C1220A8152 FOREIGN KEY (add_on_id) REFERENCES add_on (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3E89A5C13301C60 ON booking_add_on (booking_id)');
        $this->addSql('CREATE INDEX IDX_3E89A5C1220A8152 ON booking_add_on (add_on_id)');
        $this->addSql('CREATE TABLE documentation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, booking_id INTEGER NOT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, notes CLOB DEFAULT NULL, photos CLOB DEFAULT NULL --(DC2Type:json)
        , videos CLOB DEFAULT NULL --(DC2Type:json)
        , metrics CLOB DEFAULT NULL --(DC2Type:json)
        , CONSTRAINT FK_73D5A93B3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_73D5A93B3301C60 ON documentation (booking_id)');
        $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, owner_id INTEGER NOT NULL, current_location_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, age INTEGER NOT NULL, date_of_birth DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , breed VARCHAR(255) NOT NULL, gender VARCHAR(255) DEFAULT NULL, special_notes CLOB DEFAULT NULL, medical_history CLOB DEFAULT NULL, medication CLOB DEFAULT NULL, CONSTRAINT FK_629A2F187E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_629A2F18B8998A57 FOREIGN KEY (current_location_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_629A2F187E3C61F9 ON horse (owner_id)');
        $this->addSql('CREATE INDEX IDX_629A2F18B8998A57 ON horse (current_location_id)');
        $this->addSql('CREATE TABLE invitation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL, accepted BOOLEAN NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F11D61A25F37A13B ON invitation (token)');
        $this->addSql('CREATE TABLE invoice (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, booking_id INTEGER DEFAULT NULL, scale_booking_id CHAR(36) DEFAULT NULL --(DC2Type:guid)
        , stripe_payment_id VARCHAR(255) DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(255) NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_90651744A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_906517443301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9065174477C02832 FOREIGN KEY (scale_booking_id) REFERENCES scale_booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_90651744A76ED395 ON invoice (user_id)');
        $this->addSql('CREATE INDEX IDX_906517443301C60 ON invoice (booking_id)');
        $this->addSql('CREATE INDEX IDX_9065174477C02832 ON invoice (scale_booking_id)');
        $this->addSql('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, invoice_id INTEGER NOT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, booking_type VARCHAR(255) DEFAULT NULL, booking_id CHAR(36) DEFAULT NULL --(DC2Type:guid)
        , CONSTRAINT FK_1DDE477B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1DDE477B2989F1FD ON invoice_item (invoice_id)');
        $this->addSql('CREATE TABLE package (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, duration INTEGER NOT NULL, daily_rate NUMERIC(10, 2) NOT NULL)');
        $this->addSql('CREATE TABLE pricing_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(255) NOT NULL, target VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, unit VARCHAR(255) NOT NULL, active_from DATE DEFAULT NULL, active_to DATE DEFAULT NULL, notes CLOB DEFAULT NULL, requires_subscription BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE scale_booking (id CHAR(36) NOT NULL --(DC2Type:guid)
        , horse_id INTEGER NOT NULL, owner_id INTEGER NOT NULL, slot DATETIME NOT NULL, weight DOUBLE PRECISION DEFAULT NULL, booking_type VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, qr_token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_EA95521776B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EA9552177E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EA9552171AE26361 ON scale_booking (qr_token)');
        $this->addSql('CREATE INDEX IDX_EA95521776B275AD ON scale_booking (horse_id)');
        $this->addSql('CREATE INDEX IDX_EA9552177E3C61F9 ON scale_booking (owner_id)');
        $this->addSql('CREATE TABLE stall_unit (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, area VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, monthly_rent NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL)');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, horse_id INTEGER DEFAULT NULL, stall_unit_id INTEGER DEFAULT NULL, subscription_type VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, starts_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , next_due DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , end_date DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , interval VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, auto_renew BOOLEAN NOT NULL, CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A3C664D376B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A3C664D326077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D376B275AD ON subscription (horse_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D326077DEC ON subscription (stall_unit_id)');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, stall_unit_id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, due_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, origin VARCHAR(255) NOT NULL, related_id CHAR(36) DEFAULT NULL --(DC2Type:guid)
        , CONSTRAINT FK_527EDB2526077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_527EDB2526077DEC ON task (stall_unit_id)');
        $this->addSql('CREATE TABLE task_assignment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, task_id INTEGER NOT NULL, user VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2CD60F158DB60186 ON task_assignment (task_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , role VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL)');
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
        $this->addSql('DROP TABLE add_on');
        $this->addSql('DROP TABLE agreement');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE booking_add_on');
        $this->addSql('DROP TABLE documentation');
        $this->addSql('DROP TABLE horse');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_item');
        $this->addSql('DROP TABLE package');
        $this->addSql('DROP TABLE pricing_rule');
        $this->addSql('DROP TABLE scale_booking');
        $this->addSql('DROP TABLE stall_unit');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_assignment');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
