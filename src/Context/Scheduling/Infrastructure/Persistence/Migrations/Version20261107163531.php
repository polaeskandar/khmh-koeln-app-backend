<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261107163531 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling.session ADD erased_roster INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE scheduling.session ADD erased_present INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE scheduling.session ADD erased_roster_present INT DEFAULT 0 NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE scheduling.session ADD CONSTRAINT session_erased_check CHECK (
              erased_roster >= 0 AND erased_present >= 0 AND erased_roster_present >= 0
              AND erased_roster_present <= LEAST(erased_roster, erased_present)
            )
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling.session DROP CONSTRAINT session_erased_check');
        $this->addSql('ALTER TABLE scheduling.session DROP erased_roster');
        $this->addSql('ALTER TABLE scheduling.session DROP erased_present');
        $this->addSql('ALTER TABLE scheduling.session DROP erased_roster_present');
    }
}
