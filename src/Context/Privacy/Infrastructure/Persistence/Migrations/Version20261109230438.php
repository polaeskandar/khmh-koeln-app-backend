<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261109230438 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE privacy.data_request ADD erases_on DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE privacy.data_request ALTER status TYPE VARCHAR(9)');
        $this->addSql(<<<'SQL'
            ALTER TABLE privacy.data_request
              DROP CONSTRAINT data_request_status_check,
              ADD CONSTRAINT data_request_status_check CHECK (status IN ('open', 'done', 'declined', 'withdrawn')),
              ADD CONSTRAINT data_request_erases_on_check CHECK (erases_on IS NULL OR (kind = 'erasure' AND channel = 'app'))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE privacy.data_request
              DROP CONSTRAINT data_request_erases_on_check,
              DROP CONSTRAINT data_request_status_check,
              ADD CONSTRAINT data_request_status_check CHECK (status IN ('open', 'done', 'declined'))
            SQL);
        $this->addSql('ALTER TABLE privacy.data_request DROP erases_on');
        $this->addSql('ALTER TABLE privacy.data_request ALTER status TYPE VARCHAR(8)');
    }
}
