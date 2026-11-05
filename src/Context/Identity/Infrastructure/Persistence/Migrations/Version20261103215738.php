<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261103215738 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.api_token (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    device_name VARCHAR(100) DEFAULT NULL,
                    expires_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    last_used_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    revoked_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    user_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql(
            'ALTER TABLE identity.api_token ADD CONSTRAINT api_token_expiry_check CHECK ((expires_at > created_at))',
        );
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.api_token ADD CONSTRAINT api_token_revoked_after_check CHECK ((revoked_at IS NULL) OR (revoked_at >= created_at))
            SQL);
        $this->addSql('CREATE INDEX api_token_user_idx ON identity.api_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX api_token_hash_unique ON identity.api_token (token_hash)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.api_token
                ADD
                  CONSTRAINT FK_80D28A05A76ED395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.api_token DROP CONSTRAINT FK_80D28A05A76ED395');
        $this->addSql('DROP TABLE identity.api_token');
    }
}
