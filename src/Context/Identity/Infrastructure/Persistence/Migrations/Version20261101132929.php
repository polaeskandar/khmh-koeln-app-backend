<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101132929 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA identity');
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.app_user (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    language VARCHAR(2) NOT NULL,
                    email VARCHAR(254) NOT NULL,
                    display_name VARCHAR(120) NOT NULL,
                    registered_as VARCHAR(7) DEFAULT NULL,
                    registration_note TEXT DEFAULT NULL,
                    privacy_accepted_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    privacy_version VARCHAR(20) DEFAULT NULL,
                    birth_date DATE DEFAULT NULL,
                    street VARCHAR(120) DEFAULT NULL,
                    postal_code VARCHAR(10) DEFAULT NULL,
                    city VARCHAR(80) DEFAULT NULL,
                    approved_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    disabled_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    approved_by UUID DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX app_user_email_unique ON identity.app_user (email)');
        $this->addSql('CREATE INDEX IDX_2C8E78B64EA3CB3D ON identity.app_user (approved_by)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.app_user
                ADD
                  CONSTRAINT FK_2C8E78B64EA3CB3D FOREIGN KEY (approved_by) REFERENCES identity.app_user (id) ON DELETE
                SET
                  NULL NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_email_check CHECK (((email)::text = lower(btrim((email)::text))))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_display_name_check CHECK ((btrim((display_name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_language_check CHECK (((language)::text = ANY ((ARRAY['en'::character varying, 'de'::character varying, 'ar'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_registered_as_check CHECK (((registered_as)::text = ANY ((ARRAY['parent'::character varying, 'servant'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_registration_cleared_check CHECK (((approved_at IS NULL) OR ((registered_as IS NULL) AND (registration_note IS NULL))))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_address_check CHECK ((((street IS NULL) = (postal_code IS NULL)) AND ((street IS NULL) = (city IS NULL))))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity.app_user');
        $this->addSql('DROP SCHEMA identity');
    }
}
