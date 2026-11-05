<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261103225721 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meetings.meeting_session ADD title VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE meetings.meeting_session ADD location VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE meetings.meeting_schedule ADD one_off BOOLEAN DEFAULT false NOT NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_title_check CHECK ((title IS NULL AND location IS NULL) OR btrim(title) <> '')
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_one_off_check CHECK ((NOT one_off) OR (NOT active))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meetings.meeting_schedule DROP CONSTRAINT meeting_schedule_one_off_check');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP CONSTRAINT meeting_session_title_check');
        $this->addSql('ALTER TABLE meetings.meeting_schedule DROP one_off');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP title');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP location');
    }
}
