<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101132930 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE organization.registration_service (
                  account_id UUID NOT NULL,
                  service_id UUID NOT NULL,
                  PRIMARY KEY (account_id, service_id)
                )
            SQL);
        $this->addSql('CREATE INDEX IDX_AA05063FED5CA9E6 ON organization.registration_service (service_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organization.registration_service
                ADD
                  CONSTRAINT FK_AA05063FED5CA9E6 FOREIGN KEY (service_id) REFERENCES organization.service (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization.registration_service DROP CONSTRAINT FK_AA05063FED5CA9E6');
        $this->addSql('DROP TABLE organization.registration_service');
    }
}
