<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261110045223 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                UPDATE people.person
                SET
                  first_name = normalize(first_name, NFC),
                  last_name = normalize(last_name, NFC),
                  display_name = normalize(display_name, NFC),
                  name_key = normalize(name_key, NFC)
                WHERE first_name IS NOT NFC NORMALIZED
                  OR last_name IS NOT NFC NORMALIZED
                  OR display_name IS NOT NFC NORMALIZED
                  OR name_key IS NOT NFC NORMALIZED
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The forms the names had before NFC are not kept.');
    }
}
