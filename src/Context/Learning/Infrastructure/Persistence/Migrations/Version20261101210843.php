<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101210843 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE learning.homework_assignment (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    stage_id UUID NOT NULL,
                    resources JSONB DEFAULT '[]' NOT NULL,
                    created_by UUID DEFAULT NULL,
                    title VARCHAR(120) NOT NULL,
                    instructions TEXT NOT NULL,
                    due_date DATE DEFAULT NULL,
                    status VARCHAR(9) NOT NULL,
                    published_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    archived_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    lesson_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX homework_assignment_stage_status_idx ON learning.homework_assignment (stage_id, status)
            SQL);
        $this->addSql('CREATE INDEX IDX_A5F6EC5DCDF80196 ON learning.homework_assignment (lesson_id)');
        $this->addSql(<<<'SQL'
                CREATE TABLE learning.homework_completion (
                  id UUID NOT NULL,
                  person_id UUID NOT NULL,
                  completed_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    completed_by UUID DEFAULT NULL,
                    undone_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    undone_by UUID DEFAULT NULL,
                    assignment_id UUID NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX homework_completion_person_idx ON learning.homework_completion (person_id)');
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX homework_completion_current_unique ON learning.homework_completion (assignment_id, person_id)
                WHERE
                  (undone_at IS NULL)
            SQL);
        $this->addSql('CREATE INDEX IDX_ADE59F99D19302F8 ON learning.homework_completion (assignment_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  learning.homework_assignment
                ADD
                  CONSTRAINT FK_A5F6EC5DCDF80196 FOREIGN KEY (lesson_id) REFERENCES learning.lesson (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  learning.homework_completion
                ADD
                  CONSTRAINT FK_ADE59F99D19302F8 FOREIGN KEY (assignment_id) REFERENCES learning.homework_assignment (id) ON DELETE RESTRICT NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_assignment ADD CONSTRAINT homework_assignment_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('published'::character varying)::text, ('archived'::character varying)::text])))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_assignment ADD CONSTRAINT homework_assignment_published_check CHECK ((((status)::text <> 'published'::text) OR (published_at IS NOT NULL)))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_assignment ADD CONSTRAINT homework_assignment_archived_check CHECK ((((status)::text = 'archived'::text) = (archived_at IS NOT NULL)))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_assignment ADD CONSTRAINT homework_assignment_instructions_check CHECK (((btrim(instructions) <> ''::text) AND (char_length(instructions) <= 2000)))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_assignment ADD CONSTRAINT homework_assignment_title_check CHECK ((btrim((title)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_assignment ADD CONSTRAINT homework_assignment_resources_check CHECK ((jsonb_array_length(resources) <= 10))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_completion ADD CONSTRAINT homework_completion_undone_check CHECK (((undone_at IS NOT NULL) OR (undone_by IS NULL)))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE learning.homework_completion ADD CONSTRAINT homework_completion_order_check CHECK ((undone_at >= completed_at))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learning.homework_assignment DROP CONSTRAINT FK_A5F6EC5DCDF80196');
        $this->addSql('ALTER TABLE learning.homework_completion DROP CONSTRAINT FK_ADE59F99D19302F8');
        $this->addSql('DROP TABLE learning.homework_assignment');
        $this->addSql('DROP TABLE learning.homework_completion');
    }
}
