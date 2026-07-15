<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track one authenticated app opening per user and day for streaks and weekly rank movement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_activity_day (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, activity_date DATE NOT NULL, global_rank INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_1755CB10A76ED395 (user_id), UNIQUE INDEX uniq_user_activity_day (user_id, activity_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_activity_day ADD CONSTRAINT FK_1755CB10A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_activity_day');
    }
}
