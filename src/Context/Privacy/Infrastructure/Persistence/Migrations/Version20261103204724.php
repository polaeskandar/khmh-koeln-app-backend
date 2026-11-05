<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261103204724 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA privacy');
        $this->addSql(<<<'SQL'
                CREATE TABLE privacy.data_request (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    kind VARCHAR(7) NOT NULL,
                    due_on DATE NOT NULL,
                    note TEXT DEFAULT NULL,
                    status VARCHAR(8) NOT NULL,
                    outcome JSONB DEFAULT NULL,
                    person_id UUID DEFAULT NULL,
                    app_user_id UUID DEFAULT NULL,
                    requested_by VARCHAR(200) NOT NULL,
                    channel VARCHAR(9) NOT NULL,
                    received_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    closed_at TIMESTAMP(0)
                  WITH
                    TIME ZONE DEFAULT NULL,
                    closed_by UUID DEFAULT NULL,
                    decline_reason TEXT DEFAULT NULL,
                    erased_subject_kind VARCHAR(7) DEFAULT NULL,
                    erased_subject_group VARCHAR(120) DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX data_request_status_due_idx ON privacy.data_request (status, due_on)');
        $this->addSql(<<<'SQL'
            ALTER TABLE privacy.data_request
              ADD CONSTRAINT data_request_kind_check CHECK (kind IN ('erasure', 'copy')),
              ADD CONSTRAINT data_request_channel_check CHECK (channel IN ('app', 'email', 'letter', 'in_person')),
              ADD CONSTRAINT data_request_status_check CHECK (status IN ('open', 'done', 'declined')),
              ADD CONSTRAINT data_request_erased_kind_check CHECK (erased_subject_kind IN ('member', 'account')),
              ADD CONSTRAINT data_request_closed_check CHECK ((status = 'open') = (closed_at IS NULL)),
              ADD CONSTRAINT data_request_declined_check CHECK (status <> 'declined' OR decline_reason IS NOT NULL),
              ADD CONSTRAINT data_request_outcome_check CHECK (outcome IS NULL OR (status = 'done' AND kind = 'erasure')),
              ADD CONSTRAINT data_request_subject_check
                CHECK (person_id IS NOT NULL OR app_user_id IS NOT NULL OR erased_subject_kind IS NOT NULL),
              ADD CONSTRAINT data_request_note_check CHECK (char_length(note) <= 2000)
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE privacy.data_request');
        $this->addSql('DROP SCHEMA privacy');
    }
}
