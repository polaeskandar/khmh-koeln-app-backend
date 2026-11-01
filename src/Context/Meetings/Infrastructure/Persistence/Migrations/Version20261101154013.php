<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101154013 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA meetings');
        $this->addSql(<<<'SQL'
                CREATE TABLE meetings.meeting_session (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    occurrence_date DATE NOT NULL,
                    status VARCHAR(9) NOT NULL,
                    cancel_reason VARCHAR(200) DEFAULT NULL,
                    opened_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    opened_by UUID DEFAULT NULL,
                    closed_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    closed_by UUID DEFAULT NULL,
                    schedule_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX meeting_session_occurrence_date_idx ON meetings.meeting_session (occurrence_date)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX meeting_session_occurrence_unique ON meetings.meeting_session (schedule_id, occurrence_date)
            SQL);
        $this->addSql('CREATE INDEX IDX_5FF3BA94A40BC2D5 ON meetings.meeting_session (schedule_id)');
        $this->addSql(<<<'SQL'
                CREATE TABLE meetings.session_event (
                  id UUID NOT NULL,
                  type VARCHAR(8) NOT NULL,
                  occurred_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    note VARCHAR(200) DEFAULT NULL,
                    actor_id UUID DEFAULT NULL,
                    by_generator BOOLEAN NOT NULL,
                    session_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX session_event_session_occurred_idx ON meetings.session_event (session_id, occurred_at)
            SQL);
        $this->addSql('CREATE INDEX IDX_74D8D157613FECDF ON meetings.session_event (session_id)');
        $this->addSql(<<<'SQL'
                CREATE TABLE meetings.meeting_schedule (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    active BOOLEAN NOT NULL,
                    service_id UUID NOT NULL,
                    stage_id UUID DEFAULT NULL,
                    roster_mode VARCHAR(8) NOT NULL,
                    name VARCHAR(120) NOT NULL,
                    description VARCHAR(500) DEFAULT NULL,
                    location VARCHAR(120) DEFAULT NULL,
                    frequency VARCHAR(7) NOT NULL,
                    interval SMALLINT NOT NULL,
                    weekdays SMALLINT NOT NULL,
                    month_week SMALLINT DEFAULT NULL,
                    starts_on DATE NOT NULL,
                    ends_on DATE DEFAULT NULL,
                    start_time TIME(0) WITHOUT TIME ZONE NOT NULL,
                    end_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  meetings.meeting_session
                ADD
                  CONSTRAINT FK_5FF3BA94A40BC2D5 FOREIGN KEY (schedule_id) REFERENCES meetings.meeting_schedule (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  meetings.session_event
                ADD
                  CONSTRAINT FK_74D8D157613FECDF FOREIGN KEY (session_id) REFERENCES meetings.meeting_session (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_name_check CHECK ((btrim((name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_roster_mode_check CHECK (((roster_mode)::text = ANY ((ARRAY['expected'::character varying, 'open'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_times_check CHECK (((end_time IS NULL) OR (end_time > start_time)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_interval_check CHECK ((("interval" >= 1) AND ("interval" <= 52)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_weekdays_check CHECK (((weekdays >= 1) AND (weekdays <= 127)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_frequency_check CHECK (((frequency)::text = ANY ((ARRAY['weekly'::character varying, 'monthly'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_month_week_check CHECK (((((frequency)::text = 'monthly'::text) = (month_week IS NOT NULL)) AND ((month_week IS NULL) OR ((month_week = ANY (ARRAY[1, 2, 3, 4, '-1'::integer])) AND (weekdays = ANY (ARRAY[1, 2, 4, 8, 16, 32, 64]))))))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_schedule ADD CONSTRAINT meeting_schedule_dates_check CHECK (((ends_on IS NULL) OR (ends_on >= starts_on)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_status_check CHECK (((status)::text = ANY ((ARRAY['scheduled'::character varying, 'active'::character varying, 'completed'::character varying, 'canceled'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_cancel_reason_check CHECK (((cancel_reason IS NULL) OR ((status)::text = 'canceled'::text)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_closed_check CHECK ((((status)::text = 'completed'::text) = (closed_at IS NOT NULL)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.meeting_session ADD CONSTRAINT meeting_session_opened_check CHECK ((((status)::text <> ALL ((ARRAY['active'::character varying, 'completed'::character varying])::text[])) OR (opened_at IS NOT NULL)))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.session_event ADD CONSTRAINT session_event_type_check CHECK (((type)::text = ANY ((ARRAY['created'::character varying, 'opened'::character varying, 'closed'::character varying, 'reopened'::character varying, 'canceled'::character varying, 'edited'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE meetings.session_event ADD CONSTRAINT session_event_generator_check CHECK (((NOT by_generator) OR (((type)::text = 'created'::text) AND (actor_id IS NULL))))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE meetings.session_event');
        $this->addSql('DROP TABLE meetings.meeting_session');
        $this->addSql('DROP TABLE meetings.meeting_schedule');
        $this->addSql('DROP SCHEMA meetings');
    }
}
