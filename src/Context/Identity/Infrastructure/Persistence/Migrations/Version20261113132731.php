<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113132731 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM identity.app_user WHERE approved_at IS NULL');
        $this->addSql('ALTER TABLE identity.email_confirmation_token DROP CONSTRAINT fk_17d0729ba76ed395');
        $this->addSql('DROP TABLE identity.email_confirmation_token');
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT fk_2c8e78b64ea3cb3d');
        $this->addSql('DROP INDEX identity.idx_2c8e78b64ea3cb3d');
        $this->addSql('ALTER TABLE identity.app_user DROP registered_as');
        $this->addSql('ALTER TABLE identity.app_user DROP registration_note');
        $this->addSql('ALTER TABLE identity.app_user DROP approved_at');
        $this->addSql('ALTER TABLE identity.app_user DROP approved_by');
        $this->addSql('ALTER TABLE identity.app_user DROP email_confirmed_at');
    }

    #[Override]
    public function down(Schema $schema): void
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
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX email_confirmation_token_hash_unique ON identity.email_confirmation_token (token_hash)
            SQL);
        $this->addSql('CREATE INDEX email_confirmation_token_user_idx ON identity.email_confirmation_token (user_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.email_confirmation_token
                ADD
                  CONSTRAINT fk_17d0729ba76ed395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql('ALTER TABLE identity.app_user ADD registered_as VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD registration_note TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD approved_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD approved_by UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD email_confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.app_user
                ADD
                  CONSTRAINT fk_2c8e78b64ea3cb3d FOREIGN KEY (approved_by) REFERENCES identity.app_user (id) ON DELETE
                SET
                  NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql('CREATE INDEX idx_2c8e78b64ea3cb3d ON identity.app_user (approved_by)');
        $this->addSql('UPDATE identity.app_user SET approved_at = created_at, email_confirmed_at = created_at');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_registered_as_check
                CHECK (registered_as IN ('parent', 'servant', 'member'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_registration_cleared_check
                CHECK (((approved_at IS NULL) OR ((registered_as IS NULL) AND (registration_note IS NULL))))
            SQL);
    }
}
