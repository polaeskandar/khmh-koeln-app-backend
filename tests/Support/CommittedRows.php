<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class CommittedRows
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, list<string>> $idsByTable tables in the order their rows can be deleted
     */
    public function delete(array $idsByTable): void
    {
        foreach ($idsByTable as $table => $ids) {
            $this->connection->executeStatement(
                'DELETE FROM ' . $table . ' WHERE id IN (?)',
                [$ids],
                [ArrayParameterType::STRING],
            );
        }
    }
}
