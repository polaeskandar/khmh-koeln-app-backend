<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101210550 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA learning');
        $this->addSql(<<<'SQL'
                CREATE TABLE learning.lesson (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    stage_id UUID NOT NULL,
                    responsible_id UUID DEFAULT NULL,
                    materials JSONB DEFAULT '[]' NOT NULL,
                    schedule_id UUID DEFAULT NULL,
                    teaching_date DATE NOT NULL,
                    title VARCHAR(160) NOT NULL,
                    summary VARCHAR(1000) DEFAULT NULL,
                    passage VARCHAR(200) DEFAULT NULL,
                    memory_verse VARCHAR(500) DEFAULT NULL,
                    objectives VARCHAR(4000) DEFAULT NULL,
                    teaching_points VARCHAR(4000) DEFAULT NULL,
                    activities VARCHAR(4000) DEFAULT NULL,
                    notes VARCHAR(4000) DEFAULT NULL,
                    status VARCHAR(9) NOT NULL,
                    completed_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    archived_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX lesson_stage_date_idx ON learning.lesson (stage_id, teaching_date)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX lesson_meeting_unique ON learning.lesson (schedule_id, teaching_date)
                WHERE
                  (archived_at IS NULL)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.lesson ADD CONSTRAINT lesson_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('ready'::character varying)::text, ('completed'::character varying)::text])))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.lesson ADD CONSTRAINT lesson_completed_check CHECK ((((status)::text = 'completed'::text) = (completed_at IS NOT NULL)))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.lesson ADD CONSTRAINT lesson_title_check CHECK ((btrim((title)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.lesson ADD CONSTRAINT lesson_materials_check CHECK ((jsonb_array_length(materials) <= 10))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE learning.lesson');
        $this->addSql('DROP SCHEMA learning');
    }
}
