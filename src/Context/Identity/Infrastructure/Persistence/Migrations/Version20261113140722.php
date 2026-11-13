<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113140722 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.app_user ADD username VARCHAR(32) DEFAULT NULL');
        $this->addSql(<<<'SQL'
                WITH cleaned AS (
                  SELECT id, created_at, regexp_replace(regexp_replace(regexp_replace(
                    regexp_replace(lower(split_part(email, '@', 1)), '[^a-z0-9._-]', '', 'g'),
                    '([._-])[._-]+', '\1', 'g'), '^[^a-z]+', ''), '[._-]+$', '') AS name
                  FROM identity.app_user
                ), padded AS (
                  SELECT id, created_at,
                    regexp_replace(left(CASE WHEN length(name) >= 3 THEN name ELSE 'user' || name END, 29), '[._-]+$', '')
                      AS name
                  FROM cleaned
                ), numbered AS (
                  SELECT id, name, row_number() OVER (PARTITION BY name ORDER BY created_at, id) AS n FROM padded
                )
                UPDATE identity.app_user u
                SET username = CASE WHEN numbered.n = 1 THEN numbered.name ELSE numbered.name || numbered.n END
                FROM numbered
                WHERE u.id = numbered.id
            SQL);
        $this->addSql('ALTER TABLE identity.app_user ALTER username SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX app_user_username_unique ON identity.app_user (username)');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_username_check
                CHECK (username ~ '^[a-z][a-z0-9._-]{1,30}[a-z0-9]$' AND username !~ '[._-]{2}')
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX identity.app_user_username_unique');
        $this->addSql('ALTER TABLE identity.app_user DROP username');
    }
}
