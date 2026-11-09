<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261109230453 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE privacy.copy_link (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    recipient_id UUID DEFAULT NULL,
                    used_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    data_request_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX copy_link_request_idx ON privacy.copy_link (data_request_id)');
        $this->addSql('CREATE UNIQUE INDEX copy_link_hash_unique ON privacy.copy_link (token_hash)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  privacy.copy_link
                ADD
                  CONSTRAINT FK_8AE57C5E8D4B827C FOREIGN KEY (data_request_id) REFERENCES privacy.data_request (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE privacy.copy_link ADD CONSTRAINT copy_link_expiry_check CHECK ((expires_at > created_at))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE privacy.copy_link DROP CONSTRAINT FK_8AE57C5E8D4B827C');
        $this->addSql('DROP TABLE privacy.copy_link');
    }
}
