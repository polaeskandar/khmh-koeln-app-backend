<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261104023027 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learning.homework_assignment ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE learning.lesson ADD version INT DEFAULT 1 NOT NULL');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learning.homework_assignment DROP version');
        $this->addSql('ALTER TABLE learning.lesson DROP version');
    }
}
