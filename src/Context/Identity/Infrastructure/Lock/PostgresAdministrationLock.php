<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Lock;

use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Library\Checksum\Model\Checksum;
use Doctrine\DBAL\Connection;
use Override;

final readonly class PostgresAdministrationLock implements AdministrationLockInterface
{
    public const string RESOURCE = 'identity.administration';

    private const string LOCK = 'SELECT pg_advisory_xact_lock(?)';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function whileLocked(callable $work): void
    {
        $this->connection->transactional(function () use ($work): void {
            $this->connection->executeStatement(self::LOCK, [Checksum::crc32(self::RESOURCE)]);
            $work();
        });
    }
}
