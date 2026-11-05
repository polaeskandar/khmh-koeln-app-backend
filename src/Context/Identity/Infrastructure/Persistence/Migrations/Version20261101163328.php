<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101163328 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.password_token (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    used_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    user_id UUID NOT NULL,
                    created_by UUID DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX password_token_user_idx ON identity.password_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX password_token_hash_unique ON identity.password_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_B9AAF576DE12AB56 ON identity.password_token (created_by)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.password_token
                ADD
                  CONSTRAINT FK_B9AAF576A76ED395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.password_token
                ADD
                  CONSTRAINT FK_B9AAF576DE12AB56 FOREIGN KEY (created_by) REFERENCES identity.app_user (id) ON DELETE
                SET
                  NULL NOT DEFERRABLE
            SQL);
        $this->addSql('ALTER TABLE identity.app_user ADD auth_version INT DEFAULT 0 NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE identity.password_token ADD CONSTRAINT password_token_expiry_check CHECK ((expires_at > created_at))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.password_token DROP CONSTRAINT FK_B9AAF576A76ED395');
        $this->addSql('ALTER TABLE identity.password_token DROP CONSTRAINT FK_B9AAF576DE12AB56');
        $this->addSql('DROP TABLE identity.password_token');
        $this->addSql('ALTER TABLE identity.app_user DROP auth_version');
    }
}
