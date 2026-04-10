<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique source guard to XP transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_xp_source_reference ON xp_transaction (source_type, source_reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_xp_source_reference ON xp_transaction');
    }
}
