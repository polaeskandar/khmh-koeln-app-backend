<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261109234542 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                UPDATE identity.role SET scope = 'assignedGroups' WHERE name_key IN ('service_leader', 'servant')
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                UPDATE identity.role SET scope = 'church' WHERE name_key IN ('service_leader', 'servant')
            SQL);
    }
}
