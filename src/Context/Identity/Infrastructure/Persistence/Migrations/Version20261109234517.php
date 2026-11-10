<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261109234517 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.role ADD scope VARCHAR(14) DEFAULT \'church\' NOT NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.role ADD CONSTRAINT role_scope_check
                CHECK (scope IN ('church', 'assignedGroups'))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.role DROP CONSTRAINT role_scope_check');
        $this->addSql('ALTER TABLE identity.role DROP scope');
    }
}
