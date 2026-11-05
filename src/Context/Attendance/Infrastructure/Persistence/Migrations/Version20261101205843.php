<?php

declare(strict_types=1);

namespace App\Context\Attendance\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101205843 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA attendance');
        $this->addSql(<<<'SQL'
                CREATE TABLE attendance.attendance (
                  id UUID NOT NULL,
                  session_id UUID NOT NULL,
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
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX attendance_session_idx ON attendance.attendance (session_id)');
        $this->addSql('CREATE INDEX attendance_person_idx ON attendance.attendance (person_id)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX uniq_attendance_current ON attendance.attendance (session_id, person_id)
                WHERE
                  (removed_at IS NULL)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance.attendance ADD CONSTRAINT attendance_removal_check CHECK (((removed_at IS NOT NULL) OR ((removed_by IS NULL) AND (removal_reason IS NULL))))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attendance.attendance ADD CONSTRAINT attendance_removed_after_check CHECK ((removed_at >= recorded_at))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE attendance.attendance');
        $this->addSql('DROP SCHEMA attendance');
    }
}
