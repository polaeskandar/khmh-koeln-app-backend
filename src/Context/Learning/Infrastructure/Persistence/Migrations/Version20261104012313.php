<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261104012313 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE learning.lesson_collaborator (
                  user_id UUID NOT NULL,
                  lesson_id UUID NOT NULL,
                  PRIMARY KEY (lesson_id, user_id)
                )
            SQL);
        $this->addSql('CREATE INDEX lesson_collaborator_user_idx ON learning.lesson_collaborator (user_id)');
        $this->addSql('CREATE INDEX IDX_251FC8AFCDF80196 ON learning.lesson_collaborator (lesson_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  learning.lesson_collaborator
                ADD
                  CONSTRAINT FK_251FC8AFCDF80196 FOREIGN KEY (lesson_id) REFERENCES learning.lesson (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learning.lesson_collaborator DROP CONSTRAINT FK_251FC8AFCDF80196');
        $this->addSql('DROP TABLE learning.lesson_collaborator');
    }
}
