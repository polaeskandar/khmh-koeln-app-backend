<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101134009 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE public.sessions (
                  sess_id VARCHAR(128) NOT NULL,
                  sess_data BYTEA NOT NULL,
                  sess_lifetime INTEGER NOT NULL,
                  sess_time INTEGER NOT NULL,
                  PRIMARY KEY (sess_id)
                )
            SQL);
        $this->addSql('CREATE INDEX sessions_sess_lifetime_idx ON public.sessions (sess_lifetime)');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public.sessions');
    }
}
