<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261109234541 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE organization.staff_assignment (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    account_id UUID NOT NULL,
                    service_id UUID NOT NULL,
                    stage_id UUID DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX staff_assignment_service_unique ON organization.staff_assignment (account_id, service_id)
                WHERE
                  (stage_id IS NULL)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX staff_assignment_stage_unique ON organization.staff_assignment (account_id, stage_id)
                WHERE
                  (stage_id IS NOT NULL)
            SQL);
        $this->addSql('CREATE INDEX IDX_C36EEFF0ED5CA9E6 ON organization.staff_assignment (service_id)');
        $this->addSql('CREATE INDEX IDX_C36EEFF02298D193 ON organization.staff_assignment (stage_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organization.staff_assignment
                ADD
                  CONSTRAINT FK_C36EEFF0ED5CA9E6 FOREIGN KEY (service_id) REFERENCES organization.service (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organization.staff_assignment
                ADD
                  CONSTRAINT FK_C36EEFF02298D193 FOREIGN KEY (stage_id) REFERENCES organization.stage (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization.staff_assignment DROP CONSTRAINT FK_C36EEFF0ED5CA9E6');
        $this->addSql('ALTER TABLE organization.staff_assignment DROP CONSTRAINT FK_C36EEFF02298D193');
        $this->addSql('DROP TABLE organization.staff_assignment');
    }
}
