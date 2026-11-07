<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261107095843 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meetings.meeting_session ADD title VARCHAR(120) NOT NULL');
        $this->addSql('ALTER TABLE meetings.meeting_session ADD location VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE meetings.meeting_session ADD roster_mode VARCHAR(8) NOT NULL');
        $this->addSql('ALTER TABLE meetings.meeting_session ADD start_time TIME(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE meetings.meeting_session ADD end_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_title_check CHECK ((btrim((title)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_roster_mode_check CHECK (((roster_mode)::text = ANY ((ARRAY['expected'::character varying, 'open'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_times_check CHECK (((end_time IS NULL) OR (end_time > start_time)))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meetings.meeting_session DROP title');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP location');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP roster_mode');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP start_time');
        $this->addSql('ALTER TABLE meetings.meeting_session DROP end_time');
    }
}
