<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add onboarding completion flag to user profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_profile ADD has_completed_onboarding TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_profile DROP has_completed_onboarding');
    }
}
