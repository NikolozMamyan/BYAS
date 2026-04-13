<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align app_notification indexes and datetime columns with Doctrine mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_notification CHANGE created_at created_at DATETIME NOT NULL, CHANGE read_at read_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE app_notification RENAME INDEX idx_c8d8b021a76ed395 TO IDX_7DDA6C4BA76ED395');
        $this->addSql('ALTER TABLE app_notification RENAME INDEX idx_c8d8b021f8fd0ecf TO IDX_7DDA6C4B859B83FF');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_notification CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE read_at read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE app_notification RENAME INDEX IDX_7DDA6C4BA76ED395 TO idx_c8d8b021a76ed395');
        $this->addSql('ALTER TABLE app_notification RENAME INDEX IDX_7DDA6C4B859B83FF TO idx_c8d8b021f8fd0ecf');
    }
}
