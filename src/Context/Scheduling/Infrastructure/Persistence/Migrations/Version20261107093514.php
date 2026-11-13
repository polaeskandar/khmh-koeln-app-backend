<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261107093514 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('DROP SCHEMA IF EXISTS attendance CASCADE');
        $this->addSql('DROP TABLE IF EXISTS public.attendance_migration_versions');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The attendance context is part of Scheduling now.');
    }
}
