<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113143122 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                DO $$
                BEGIN
                  IF EXISTS (SELECT 1 FROM identity.app_user WHERE person_id IS NULL) THEN
                    CREATE TEMPORARY TABLE account_person ON COMMIT DROP AS
                    SELECT id AS account_id, uuidv7() AS person_id, created_at, first_name,
                      COALESCE(NULLIF(btrim(substr(full_name, length(first_name) + 2)), ''), first_name) AS last_name
                    FROM (
                      SELECT id, created_at, regexp_replace(btrim(display_name), '\s+', ' ', 'g') AS full_name,
                        split_part(regexp_replace(btrim(display_name), '\s+', ' ', 'g'), ' ', 1) AS first_name
                      FROM identity.app_user
                      WHERE person_id IS NULL
                    ) AS named;

                    INSERT INTO people.person (id, status, first_name, last_name, name_key, search_text, created_at)
                    SELECT person_id, 'active', left(first_name, 80), left(last_name, 80),
                      replace(lower(first_name || ' ' || last_name), ' ', '_'), lower(first_name || ' ' || last_name), created_at
                    FROM account_person;

                    UPDATE identity.app_user u SET person_id = a.person_id
                    FROM account_person a
                    WHERE u.id = a.account_id;
                  END IF;
                END
                $$
            SQL);
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT app_user_member_link_check');
        $this->addSql('DROP INDEX identity.app_user_person_unique');
        $this->addSql('ALTER TABLE identity.app_user ALTER person_id SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX app_user_person_unique ON identity.app_user (person_id)');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX identity.app_user_person_unique');
        $this->addSql('ALTER TABLE identity.app_user ALTER person_id DROP NOT NULL');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX app_user_person_unique ON identity.app_user (person_id)
                WHERE
                  (person_id IS NOT NULL)
            SQL);
        $this->addSql('UPDATE identity.app_user SET person_id = NULL WHERE age_check IS NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_member_link_check
                CHECK ((person_id IS NULL) = (age_check IS NULL))
            SQL);
    }
}
