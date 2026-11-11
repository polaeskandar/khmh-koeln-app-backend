<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261111201730 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                DELETE FROM organization.staff_assignment covered
                USING organization.staff_assignment whole
                WHERE covered.stage_id IS NOT NULL
                  AND whole.stage_id IS NULL
                  AND whole.account_id = covered.account_id
                  AND whole.service_id = covered.service_id
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
