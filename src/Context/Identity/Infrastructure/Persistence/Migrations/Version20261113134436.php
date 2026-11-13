<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113134436 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.password_token DROP CONSTRAINT fk_b9aaf576a76ed395');
        $this->addSql('ALTER TABLE identity.password_token DROP CONSTRAINT fk_b9aaf576de12ab56');
        $this->addSql('DROP TABLE identity.password_token');
    }

    #[Override]
    public function down(Schema $schema): void
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
        $this->addSql('CREATE INDEX idx_b9aaf576de12ab56 ON identity.password_token (created_by)');
        $this->addSql('CREATE INDEX password_token_user_idx ON identity.password_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX password_token_hash_unique ON identity.password_token (token_hash)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.password_token
                ADD
                  CONSTRAINT fk_b9aaf576a76ed395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.password_token
                ADD
                  CONSTRAINT fk_b9aaf576de12ab56 FOREIGN KEY (created_by) REFERENCES identity.app_user (id) ON DELETE
                SET
                  NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.password_token ADD CONSTRAINT password_token_expiry_check CHECK ((expires_at > created_at))
            SQL);
    }
}
