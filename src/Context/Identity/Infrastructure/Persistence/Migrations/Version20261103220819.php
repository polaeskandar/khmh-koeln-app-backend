<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261103220819 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.email_confirmation_token (
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
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX email_confirmation_token_user_idx ON identity.email_confirmation_token (user_id)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX email_confirmation_token_hash_unique ON identity.email_confirmation_token (token_hash)
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.email_confirmation_token
                ADD
                  CONSTRAINT FK_17D0729BA76ED395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql('ALTER TABLE identity.app_user ADD email_confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE identity.app_user SET email_confirmed_at = created_at');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.email_confirmation_token ADD CONSTRAINT email_confirmation_token_expiry_check CHECK ((expires_at > created_at))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.email_confirmation_token DROP CONSTRAINT FK_17D0729BA76ED395');
        $this->addSql('DROP TABLE identity.email_confirmation_token');
        $this->addSql('ALTER TABLE identity.app_user DROP email_confirmed_at');
    }
}
