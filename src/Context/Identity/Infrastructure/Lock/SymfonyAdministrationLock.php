<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Lock;

use App\Context\Identity\Domain\Service\AdministrationLockInterface;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

final readonly class SymfonyAdministrationLock implements AdministrationLockInterface
{
    public const string RESOURCE = 'identity.administration';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function whileLocked(callable $work): void
    {
        $lock = new LockFactory(new DoctrineDbalPostgreSqlStore($this->connection))->createLock(
            self::RESOURCE,
            ttl: null,
        );

        $lock->acquire(blocking: true);

        try {
            $work();
        } finally {
            $lock->release();
        }
    }
}
