<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app notifications table with read state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_notification (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, actor_user_id INT DEFAULT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(160) NOT NULL, body VARCHAR(255) NOT NULL, target_url VARCHAR(255) DEFAULT NULL, actor_name VARCHAR(120) DEFAULT NULL, actor_avatar_url VARCHAR(255) DEFAULT NULL, context_data JSON NOT NULL, is_read TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_app_notification_user_created (user_id, created_at), INDEX idx_app_notification_user_read (user_id, is_read), INDEX IDX_C8D8B021A76ED395 (user_id), INDEX IDX_C8D8B021F8FD0ECF (actor_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_notification ADD CONSTRAINT FK_C8D8B021A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_notification ADD CONSTRAINT FK_C8D8B021F8FD0ECF FOREIGN KEY (actor_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_notification');
    }
}
