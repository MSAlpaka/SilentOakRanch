<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250918182815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE add_on (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE agreement (id SERIAL NOT NULL, user_id INT NOT NULL, type VARCHAR(255) NOT NULL, version VARCHAR(255) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, consent_given BOOLEAN NOT NULL, consent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2E655A24A76ED395 ON agreement (user_id)');
        $this->addSql('COMMENT ON COLUMN agreement.consent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN agreement.signed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE appointment (id SERIAL NOT NULL, horse_id INT NOT NULL, owner_id INT NOT NULL, service_provider_id INT DEFAULT NULL, service_type_id INT NOT NULL, start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FE38F84476B275AD ON appointment (horse_id)');
        $this->addSql('CREATE INDEX IDX_FE38F8447E3C61F9 ON appointment (owner_id)');
        $this->addSql('CREATE INDEX IDX_FE38F844C6C98E06 ON appointment (service_provider_id)');
        $this->addSql('CREATE INDEX IDX_FE38F844AC8DE0F ON appointment (service_type_id)');
        $this->addSql('CREATE TABLE booking (id SERIAL NOT NULL, stall_unit_id INT NOT NULL, horse_id INT DEFAULT NULL, package_id INT DEFAULT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, "user" VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, date_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_confirmed BOOLEAN NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E00CEDDE26077DEC ON booking (stall_unit_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE76B275AD ON booking (horse_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEF44CABFF ON booking (package_id)');
        $this->addSql('CREATE TABLE booking_add_on (booking_id INT NOT NULL, add_on_id INT NOT NULL, PRIMARY KEY(booking_id, add_on_id))');
        $this->addSql('CREATE INDEX IDX_3E89A5C13301C60 ON booking_add_on (booking_id)');
        $this->addSql('CREATE INDEX IDX_3E89A5C1220A8152 ON booking_add_on (add_on_id)');
        $this->addSql('CREATE TABLE documentation (id SERIAL NOT NULL, booking_id INT NOT NULL, type VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notes TEXT DEFAULT NULL, photos JSON DEFAULT NULL, videos JSON DEFAULT NULL, metrics JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_73D5A93B3301C60 ON documentation (booking_id)');
        $this->addSql('CREATE TABLE horse (id SERIAL NOT NULL, owner_id INT NOT NULL, stall_unit_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, age INT NOT NULL, date_of_birth TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, breed VARCHAR(255) NOT NULL, gender VARCHAR(255) DEFAULT NULL, special_notes TEXT DEFAULT NULL, medical_history TEXT DEFAULT NULL, medication TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_629A2F187E3C61F9 ON horse (owner_id)');
        $this->addSql('CREATE INDEX IDX_629A2F1826077DEC ON horse (stall_unit_id)');
        $this->addSql('COMMENT ON COLUMN horse.date_of_birth IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE invitation (id SERIAL NOT NULL, email VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL, accepted BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F11D61A25F37A13B ON invitation (token)');
        $this->addSql('CREATE TABLE invoice (id SERIAL NOT NULL, user_id INT NOT NULL, booking_id INT DEFAULT NULL, scale_booking_id UUID DEFAULT NULL, stripe_payment_id VARCHAR(255) DEFAULT NULL, number VARCHAR(20) DEFAULT NULL, period VARCHAR(7) DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(255) NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_90651744A76ED395 ON invoice (user_id)');
        $this->addSql('CREATE INDEX IDX_906517443301C60 ON invoice (booking_id)');
        $this->addSql('CREATE INDEX IDX_9065174477C02832 ON invoice (scale_booking_id)');
        $this->addSql('CREATE TABLE invoice_item (id SERIAL NOT NULL, invoice_id INT NOT NULL, label VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, booking_type VARCHAR(255) DEFAULT NULL, booking_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1DDE477B2989F1FD ON invoice_item (invoice_id)');
        $this->addSql('CREATE TABLE package (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, duration INT NOT NULL, daily_rate NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE pricing_rule (id SERIAL NOT NULL, type VARCHAR(255) NOT NULL, target VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, unit VARCHAR(255) NOT NULL, active_from DATE DEFAULT NULL, active_to DATE DEFAULT NULL, notes TEXT DEFAULT NULL, requires_subscription BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE scale_booking (id UUID NOT NULL, horse_id INT NOT NULL, owner_id INT NOT NULL, slot TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, weight DOUBLE PRECISION DEFAULT NULL, booking_type VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, qr_token VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EA9552171AE26361 ON scale_booking (qr_token)');
        $this->addSql('CREATE INDEX IDX_EA95521776B275AD ON scale_booking (horse_id)');
        $this->addSql('CREATE INDEX IDX_EA9552177E3C61F9 ON scale_booking (owner_id)');
        $this->addSql('CREATE TABLE service_provider (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE service_type (id SERIAL NOT NULL, provider_type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, default_duration_minutes INT NOT NULL, base_price NUMERIC(10, 2) NOT NULL, taxable BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE stall_unit (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, area VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, monthly_rent NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE subscription (id SERIAL NOT NULL, user_id INT NOT NULL, horse_id INT DEFAULT NULL, stall_unit_id INT DEFAULT NULL, subscription_type VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, next_due TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, interval VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, auto_renew BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D376B275AD ON subscription (horse_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D326077DEC ON subscription (stall_unit_id)');
        $this->addSql('COMMENT ON COLUMN subscription.starts_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.next_due IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE task (id SERIAL NOT NULL, stall_unit_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) NOT NULL, origin VARCHAR(255) NOT NULL, related_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_527EDB2526077DEC ON task (stall_unit_id)');
        $this->addSql('CREATE TABLE task_assignment (id SERIAL NOT NULL, task_id INT NOT NULL, "user" VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2CD60F158DB60186 ON task_assignment (task_id)');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, role VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE agreement ADD CONSTRAINT FK_2E655A24A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F84476B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F8447E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844C6C98E06 FOREIGN KEY (service_provider_id) REFERENCES service_provider (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844AC8DE0F FOREIGN KEY (service_type_id) REFERENCES service_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE26077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE76B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEF44CABFF FOREIGN KEY (package_id) REFERENCES package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking_add_on ADD CONSTRAINT FK_3E89A5C13301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking_add_on ADD CONSTRAINT FK_3E89A5C1220A8152 FOREIGN KEY (add_on_id) REFERENCES add_on (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documentation ADD CONSTRAINT FK_73D5A93B3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE horse ADD CONSTRAINT FK_629A2F187E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE horse ADD CONSTRAINT FK_629A2F1826077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517443301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174477C02832 FOREIGN KEY (scale_booking_id) REFERENCES scale_booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scale_booking ADD CONSTRAINT FK_EA95521776B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scale_booking ADD CONSTRAINT FK_EA9552177E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D376B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D326077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2526077DEC FOREIGN KEY (stall_unit_id) REFERENCES stall_unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE agreement DROP CONSTRAINT FK_2E655A24A76ED395');
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT FK_FE38F84476B275AD');
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT FK_FE38F8447E3C61F9');
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT FK_FE38F844C6C98E06');
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT FK_FE38F844AC8DE0F');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE26077DEC');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE76B275AD');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEF44CABFF');
        $this->addSql('ALTER TABLE booking_add_on DROP CONSTRAINT FK_3E89A5C13301C60');
        $this->addSql('ALTER TABLE booking_add_on DROP CONSTRAINT FK_3E89A5C1220A8152');
        $this->addSql('ALTER TABLE documentation DROP CONSTRAINT FK_73D5A93B3301C60');
        $this->addSql('ALTER TABLE horse DROP CONSTRAINT FK_629A2F187E3C61F9');
        $this->addSql('ALTER TABLE horse DROP CONSTRAINT FK_629A2F1826077DEC');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_90651744A76ED395');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_906517443301C60');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_9065174477C02832');
        $this->addSql('ALTER TABLE invoice_item DROP CONSTRAINT FK_1DDE477B2989F1FD');
        $this->addSql('ALTER TABLE scale_booking DROP CONSTRAINT FK_EA95521776B275AD');
        $this->addSql('ALTER TABLE scale_booking DROP CONSTRAINT FK_EA9552177E3C61F9');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3A76ED395');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D376B275AD');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D326077DEC');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB2526077DEC');
        $this->addSql('ALTER TABLE task_assignment DROP CONSTRAINT FK_2CD60F158DB60186');
        $this->addSql('DROP TABLE add_on');
        $this->addSql('DROP TABLE agreement');
        $this->addSql('DROP TABLE appointment');
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
        $this->addSql('DROP TABLE service_provider');
        $this->addSql('DROP TABLE service_type');
        $this->addSql('DROP TABLE stall_unit');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_assignment');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
