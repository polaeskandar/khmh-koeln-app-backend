<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113150145 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                UPDATE people.person p
                SET birth_date = COALESCE(p.birth_date, u.birth_date),
                  street = CASE WHEN p.street IS NULL AND u.street IS NOT NULL THEN u.street ELSE p.street END,
                  postal_code = CASE WHEN p.street IS NULL AND u.street IS NOT NULL THEN u.postal_code ELSE p.postal_code END,
                  city = CASE WHEN p.street IS NULL AND u.street IS NOT NULL THEN u.city ELSE p.city END
                FROM identity.app_user u
                WHERE u.person_id = p.id
                  AND (u.birth_date IS NOT NULL OR u.street IS NOT NULL)
            SQL);
        $this->addSql('DROP INDEX identity.app_user_email_unique');
        $this->addSql('ALTER TABLE identity.app_user DROP email');
        $this->addSql('ALTER TABLE identity.app_user DROP display_name');
        $this->addSql('ALTER TABLE identity.app_user DROP birth_date');
        $this->addSql('ALTER TABLE identity.app_user DROP street');
        $this->addSql('ALTER TABLE identity.app_user DROP postal_code');
        $this->addSql('ALTER TABLE identity.app_user DROP city');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.app_user ADD email VARCHAR(254) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD display_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD birth_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD street VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD postal_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD city VARCHAR(80) DEFAULT NULL');
        $this->addSql(<<<'SQL'
                UPDATE identity.app_user u
                SET email = u.username || '@accounts.invalid',
                  display_name = COALESCE(left(p.display_name, 120), left(p.first_name || ' ' || p.last_name, 120), u.username)
                FROM identity.app_user a
                LEFT JOIN people.person p ON p.id = a.person_id
                WHERE a.id = u.id
            SQL);
        $this->addSql('ALTER TABLE identity.app_user ALTER email SET NOT NULL');
        $this->addSql('ALTER TABLE identity.app_user ALTER display_name SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX app_user_email_unique ON identity.app_user (email)');
    }
}
