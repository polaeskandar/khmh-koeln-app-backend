<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261103211727 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX data_request_open_app_erasure_unique ON privacy.data_request (app_user_id)
                WHERE
                  (
                    (
                      (kind):: text = 'erasure' :: text
                    )
                    AND (
                      (channel):: text = 'app' :: text
                    )
                    AND (
                      (status):: text = 'open' :: text
                    )
                  )
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX privacy.data_request_open_app_erasure_unique');
    }
}
