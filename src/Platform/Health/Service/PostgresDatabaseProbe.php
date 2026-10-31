<?php

declare(strict_types=1);

namespace App\Platform\Health\Service;

use App\Library\Strings\Model\Text;
use App\Platform\Health\Contract\DatabaseProbe;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(DatabaseProbe::class)]
final readonly class PostgresDatabaseProbe implements DatabaseProbe
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function isAvailable(int $timeoutSeconds): bool
    {
        try {
            $this->connection->transactional(static function (Connection $connection) use ($timeoutSeconds): void {
                $connection->executeStatement(
                    Text::format("SET LOCAL statement_timeout = '%ds'", $timeoutSeconds)->toString(),
                );
                $connection->fetchOne('SELECT 1');
            });
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
