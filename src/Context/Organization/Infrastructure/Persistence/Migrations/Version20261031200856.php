<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261031200856 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA organization');
        $this->addSql(<<<'SQL'
                CREATE TABLE organization.service (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    archived_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    name VARCHAR(120) NOT NULL,
                    name_key TEXT NOT NULL,
                    description TEXT DEFAULT NULL,
                    tone SMALLINT NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX service_name_key_unique ON organization.service (name_key)');
        $this->addSql(<<<'SQL'
                CREATE TABLE organization.stage (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    position INT NOT NULL,
                    archived_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    name VARCHAR(80) NOT NULL,
                    name_key TEXT NOT NULL,
                    description TEXT DEFAULT NULL,
                    service_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX stage_service_position_idx ON organization.stage (service_id, position)');
        $this->addSql('CREATE UNIQUE INDEX stage_service_name_key_unique ON organization.stage (service_id, name_key)');
        $this->addSql('CREATE UNIQUE INDEX stage_id_service_unique ON organization.stage (id, service_id)');
        $this->addSql('CREATE INDEX IDX_8C85ABCAED5CA9E6 ON organization.stage (service_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organization.stage
                ADD
                  CONSTRAINT FK_8C85ABCAED5CA9E6 FOREIGN KEY (service_id) REFERENCES organization.service (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE organization.service ADD CONSTRAINT service_tone_check CHECK (((tone >= 1) AND (tone <= 8)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE organization.service ADD CONSTRAINT service_name_check CHECK ((btrim((name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE organization.service ADD CONSTRAINT service_description_check CHECK ((char_length(description) <= 2000))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE organization.stage ADD CONSTRAINT stage_name_check CHECK ((btrim((name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE organization.stage ADD CONSTRAINT stage_description_check CHECK ((char_length(description) <= 500))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE organization.stage ADD CONSTRAINT stage_position_check CHECK (("position" >= 0))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE organization.stage');
        $this->addSql('DROP TABLE organization.service');
        $this->addSql('DROP SCHEMA organization');
    }
}
