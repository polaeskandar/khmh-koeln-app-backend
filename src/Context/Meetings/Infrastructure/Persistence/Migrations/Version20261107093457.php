<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261107093457 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE meetings.attendance (
                  id UUID NOT NULL,
                  person_id UUID NOT NULL,
                  recorded_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    recorded_by UUID DEFAULT NULL,
                    removed_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    removed_by UUID DEFAULT NULL,
                    removal_reason VARCHAR(200) DEFAULT NULL,
                    session_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX attendance_session_idx ON meetings.attendance (session_id)');
        $this->addSql('CREATE INDEX attendance_person_idx ON meetings.attendance (person_id)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX attendance_current_unique ON meetings.attendance (session_id, person_id)
                WHERE
                  (removed_at IS NULL)
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  meetings.attendance
                ADD
                  CONSTRAINT FK_5FE8D6613FECDF FOREIGN KEY (session_id) REFERENCES meetings.meeting_session (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE meetings.attendance ADD CONSTRAINT attendance_removal_check CHECK (((removed_at IS NOT NULL) OR ((removed_by IS NULL) AND (removal_reason IS NULL))))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE meetings.attendance ADD CONSTRAINT attendance_removed_after_check CHECK ((removed_at >= recorded_at))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meetings.attendance DROP CONSTRAINT FK_5FE8D6613FECDF');
        $this->addSql('DROP TABLE meetings.attendance');
    }
}
