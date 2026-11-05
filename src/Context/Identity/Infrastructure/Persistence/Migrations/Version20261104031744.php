<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261104031744 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.app_user ADD person_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD age_check VARCHAR(15) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity.app_user ADD guardian_consent_note VARCHAR(200) DEFAULT NULL');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX app_user_person_unique ON identity.app_user (person_id)
                WHERE
                  (person_id IS NOT NULL)
            SQL);
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT app_user_registered_as_check');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_registered_as_check
                CHECK (registered_as IN ('parent', 'servant', 'member'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_age_check_check
                CHECK (age_check IN ('sixteenOrOlder', 'guardianConsent'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_member_link_check
                CHECK ((person_id IS NULL) = (age_check IS NULL))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_guardian_consent_note_check
                CHECK (guardian_consent_note IS NULL OR age_check IS NOT DISTINCT FROM 'guardianConsent')
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT app_user_guardian_consent_note_check');
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT app_user_member_link_check');
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT app_user_age_check_check');
        $this->addSql('ALTER TABLE identity.app_user DROP CONSTRAINT app_user_registered_as_check');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.app_user ADD CONSTRAINT app_user_registered_as_check
                CHECK (registered_as IN ('parent', 'servant'))
            SQL);
        $this->addSql('DROP INDEX identity.app_user_person_unique');
        $this->addSql('ALTER TABLE identity.app_user DROP person_id');
        $this->addSql('ALTER TABLE identity.app_user DROP age_check');
        $this->addSql('ALTER TABLE identity.app_user DROP guardian_consent_note');
    }
}
