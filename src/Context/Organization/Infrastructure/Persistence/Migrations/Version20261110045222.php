<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261110045222 extends AbstractMigration
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
                    SELECT 'service ' || string_agg(format('%s %L', id, name), ' and service ' ORDER BY id) AS rows
                    FROM organization.service
                    GROUP BY normalize(name_key, NFC)
                    HAVING count(DISTINCT name_key) > 1
                    UNION ALL
                    SELECT 'stage ' || string_agg(format('%s %L', id, name), ' and stage ' ORDER BY id)
                    FROM organization.stage
                    GROUP BY service_id, normalize(name_key, NFC)
                    HAVING count(DISTINCT name_key) > 1
                  ) AS twin;

                  IF twins IS NOT NULL THEN
                    RAISE EXCEPTION 'Composed to NFC, these names would share one key: %', twins
                      USING HINT = 'Rename all but one of each, then migrate again.';
                  END IF;
                END
                $$
            SQL);
        $this->addSql(<<<'SQL'
                UPDATE organization.service
                SET name = normalize(name, NFC), name_key = normalize(name_key, NFC)
                WHERE name IS NOT NFC NORMALIZED OR name_key IS NOT NFC NORMALIZED
            SQL);
        $this->addSql(<<<'SQL'
                UPDATE organization.stage
                SET name = normalize(name, NFC), name_key = normalize(name_key, NFC)
                WHERE name IS NOT NFC NORMALIZED OR name_key IS NOT NFC NORMALIZED
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The forms the names had before NFC are not kept.');
    }
}
