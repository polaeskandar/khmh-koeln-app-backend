<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101203229 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE people.guardian_relationship (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    user_id UUID NOT NULL,
                    relationship VARCHAR(8) DEFAULT 'parent' NOT NULL,
                    status VARCHAR(8) NOT NULL,
                    approved_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    approved_by UUID DEFAULT NULL,
                    revoked_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    revoked_by UUID DEFAULT NULL,
                    person_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX guardian_relationship_person_idx ON people.guardian_relationship (person_id)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX guardian_relationship_current_unique ON people.guardian_relationship (user_id, person_id)
                WHERE
                  (
                    (status):: text <> 'revoked' :: text
                  )
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  people.guardian_relationship
                ADD
                  CONSTRAINT FK_4B9EFDEB217BBB47 FOREIGN KEY (person_id) REFERENCES people.person (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE people.guardian_relationship ADD CONSTRAINT guardian_relationship_relationship_check CHECK (((relationship)::text = ANY (ARRAY[('parent'::character varying)::text, ('guardian'::character varying)::text])))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE people.guardian_relationship ADD CONSTRAINT guardian_relationship_status_check CHECK (((status)::text = ANY (ARRAY[('approved'::character varying)::text, ('revoked'::character varying)::text])))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE people.guardian_relationship ADD CONSTRAINT guardian_relationship_revoked_check CHECK ((((status)::text = 'revoked'::text) = (revoked_at IS NOT NULL)))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE people.guardian_relationship DROP CONSTRAINT FK_4B9EFDEB217BBB47');
        $this->addSql('DROP TABLE people.guardian_relationship');
    }
}
