<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public Passport visits and contact intents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE public_passport_visit (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, viewer_id INT DEFAULT NULL, ip_hash VARCHAR(64) DEFAULT NULL, user_agent_hash VARCHAR(64) DEFAULT NULL, referrer VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_471B0EA1CCFA12B8 (profile_id), INDEX IDX_471B0EA16C59C752 (viewer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE public_passport_contact_intent (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, sender_id INT DEFAULT NULL, status VARCHAR(32) NOT NULL, source VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_94B9219ACCFA12B8 (profile_id), INDEX IDX_94B9219AF624B39D (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE public_passport_visit ADD CONSTRAINT FK_59787B20CCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE public_passport_visit ADD CONSTRAINT FK_59787B2073DB2B39 FOREIGN KEY (viewer_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE public_passport_contact_intent ADD CONSTRAINT FK_48763083CCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE public_passport_contact_intent ADD CONSTRAINT FK_48763083F624B39D FOREIGN KEY (sender_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE public_passport_contact_intent DROP FOREIGN KEY FK_48763083CCFA12B8');
        $this->addSql('ALTER TABLE public_passport_contact_intent DROP FOREIGN KEY FK_48763083F624B39D');
        $this->addSql('ALTER TABLE public_passport_visit DROP FOREIGN KEY FK_59787B20CCFA12B8');
        $this->addSql('ALTER TABLE public_passport_visit DROP FOREIGN KEY FK_59787B2073DB2B39');
        $this->addSql('DROP TABLE public_passport_contact_intent');
        $this->addSql('DROP TABLE public_passport_visit');
    }
}
