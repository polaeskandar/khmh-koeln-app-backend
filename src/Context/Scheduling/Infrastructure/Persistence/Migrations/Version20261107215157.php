<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261107215157 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling.schedule ADD one_off BOOLEAN DEFAULT false NOT NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE scheduling.schedule ADD CONSTRAINT schedule_one_off_check CHECK ((NOT one_off) OR (NOT active))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling.schedule DROP CONSTRAINT schedule_one_off_check');
        $this->addSql('ALTER TABLE scheduling.schedule DROP one_off');
    }
}
