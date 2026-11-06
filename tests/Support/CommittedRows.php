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
     * @param array<string, list<string>> $idsInDeletionOrder
     */
    public function delete(array $idsInDeletionOrder): void
    {
        foreach ($idsInDeletionOrder as $table => $ids) {
            $this->connection->executeStatement(
                'DELETE FROM ' . $table . ' WHERE id IN (?)',
                [$ids],
                [ArrayParameterType::STRING],
            );
        }
    }
}
