<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261031210055 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA people');
        $this->addSql(<<<'SQL'
                CREATE TABLE people.person (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    status VARCHAR(8) NOT NULL,
                    first_name VARCHAR(80) NOT NULL,
                    last_name VARCHAR(80) NOT NULL,
                    display_name VARCHAR(120) DEFAULT NULL,
                    name_key TEXT NOT NULL,
                    search_text TEXT NOT NULL,
                    birth_date DATE DEFAULT NULL,
                    street VARCHAR(120) DEFAULT NULL,
                    postal_code VARCHAR(10) DEFAULT NULL,
                    city VARCHAR(80) DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX person_name_key_idx ON people.person (name_key)');
        $this->addSql(<<<'SQL'
                CREATE TABLE people.membership (
                  id UUID NOT NULL,
                  service_id UUID NOT NULL,
                  stage_id UUID DEFAULT NULL,
                  starts_on DATE NOT NULL,
                  ends_on DATE DEFAULT NULL,
                  person_id UUID NOT NULL,
                  PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX membership_roster_idx ON people.membership (service_id, stage_id)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX membership_open_stage_unique ON people.membership (person_id, stage_id)
                WHERE
                  (
                    (ends_on IS NULL)
                    AND (stage_id IS NOT NULL)
                  )
            SQL);
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX membership_open_service_unique ON people.membership (person_id, service_id)
                WHERE
                  (
                    (ends_on IS NULL)
                    AND (stage_id IS NULL)
                  )
            SQL);
        $this->addSql('CREATE INDEX IDX_87BD3CFB217BBB47 ON people.membership (person_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  people.membership
                ADD
                  CONSTRAINT FK_87BD3CFB217BBB47 FOREIGN KEY (person_id) REFERENCES people.person (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE people.person ADD CONSTRAINT person_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE people.person ADD CONSTRAINT person_first_name_check CHECK ((btrim((first_name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE people.person ADD CONSTRAINT person_last_name_check CHECK ((btrim((last_name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE people.person ADD CONSTRAINT person_address_check CHECK ((((street IS NULL) = (postal_code IS NULL)) AND ((street IS NULL) = (city IS NULL))))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE people.membership ADD CONSTRAINT membership_dates_check CHECK (((ends_on IS NULL) OR (ends_on >= starts_on)))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE people.membership');
        $this->addSql('DROP TABLE people.person');
        $this->addSql('DROP SCHEMA people');
    }
}
