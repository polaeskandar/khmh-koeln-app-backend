<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261110045220 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                DO $$
                DECLARE
                  twins TEXT;
                BEGIN
                  SELECT string_agg(twin.rows, '; ')
                  INTO twins
                  FROM (
                    SELECT 'role ' || string_agg(format('%s %L', id, name), ' and role ' ORDER BY id) AS rows
                    FROM identity.role
                    GROUP BY normalize(name_key, NFC)
                    HAVING count(DISTINCT name_key) > 1
                  ) AS twin;

                  IF twins IS NOT NULL THEN
                    RAISE EXCEPTION 'Composed to NFC, these role names would be the same: %', twins
                      USING HINT = 'Rename all but one of each, then migrate again.';
                  END IF;
                END
                $$
            SQL);
        $this->addSql(<<<'SQL'
                UPDATE identity.role
                SET name = normalize(name, NFC), name_key = normalize(name_key, NFC)
                WHERE name IS NOT NFC NORMALIZED OR name_key IS NOT NFC NORMALIZED
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The forms the role names and emails had before NFC are not kept.');
    }
}
